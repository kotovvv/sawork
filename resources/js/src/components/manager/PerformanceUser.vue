<template>
  <v-container fluid>
    <v-row>
      <v-col cols="12">
        <v-progress-linear
          :active="loading"
          indeterminate
          color="purple"
        ></v-progress-linear>
      </v-col>
    </v-row>
    <v-row>
      <v-col>
        <div class="performance-user">
          <p>User Performance</p>
        </div>
      </v-col>
    </v-row>
    <v-row>
      <v-col>
        <Datepicker
          v-model="dateMin"
          format="yyyy-MM-dd"
          monday-first
        ></Datepicker>

        <Datepicker
          v-model="dateMax"
          format="yyyy-MM-dd"
          monday-first
        ></Datepicker>
      </v-col>

      <v-col>
        <div class="d-flex">
          <v-select
            v-if="users.length"
            v-model="IDUser"
            :items="users"
            width="200"
            multiple
            clearable
          ></v-select>
          <v-btn size="x-large" @click="performanceUsers">Report</v-btn>
        </div>
      </v-col>
      <v-spacer></v-spacer>
    </v-row>
    <v-row>
      <v-col cols="12">
        <v-data-table
          :headers="headers"
          :items="aggregatedData"
          class="elevation-1"
          @click:row="showDetails"
        >
          <template v-slot:item.userName="{ item }">
            {{ getUserName(item.IDUzytkownika) }}
          </template>

          <template v-slot:item.TotalQuantity="{ item }">
            {{ parseFloat(item.TotalQuantity).toFixed(2) }}
          </template>
          <template v-slot:item.TotalWeight="{ item }"
            >{{ parseFloat(item.TotalWeight).toFixed(2) }} kg</template
          >
          <template v-slot:item.TotalM3="{ item }"
            >{{ parseFloat(item.TotalM3).toFixed(4) }} m³</template
          >
          <template v-slot:item.avgTime="{ item }">{{ item.avgTime }}</template>
        </v-data-table>
        <v-dialog v-model="detailsDialog" max-width="1200px">
          <v-card>
            <v-card-title>
              Order Details for {{ getUserName(selectedUser) }}
            </v-card-title>
            <v-card-text>
              <v-data-table
                :headers="detailHeaders"
                :items="userOrders"
                class="elevation-1"
              >
                <template v-slot:item.Date="{ item }">
                  {{ moment(item.Date).format("DD.MM.YYYY HH:mm") }}
                </template>
                <template v-slot:item.TotalQuantity="{ item }">
                  {{ parseFloat(item.TotalQuantity).toFixed(2) }}
                </template>
                <template v-slot:item.TotalWeight="{ item }">
                  {{ parseFloat(item.TotalWeight).toFixed(2) }} kg
                </template>
                <template v-slot:item.TotalM3="{ item }">
                  {{ parseFloat(item.TotalM3).toFixed(4) }} m³
                </template>
                <template v-slot:item.minutes_to_pack="{ item }">
                  {{ item.minutes_to_pack }}
                </template>
              </v-data-table>
            </v-card-text>
            <v-card-actions>
              <v-spacer></v-spacer>
              <v-btn color="blue darken-1" text @click="detailsDialog = false">
                Close
              </v-btn>
            </v-card-actions>
          </v-card>
        </v-dialog>
      </v-col>
    </v-row>
  </v-container>
</template>

<script>
import axios from "axios";
import Datepicker from "vuejs3-datepicker";
import moment from "moment";
export default {
  name: "FulstorPerformanceUser",
  components: {
    Datepicker,
  },
  data() {
    return {
      loading: false,
      IDUser: [],
      dateMin: moment().subtract(1, "day").format("YYYY-MM-DD"),
      dateMax: moment().format("YYYY-MM-DD"),
      data: [],
      users: [],
      headers: [
        { title: "User", value: "userName" },
        { title: "OrderCount", value: "OrderCount" },
        { title: "Total Quantity", value: "TotalQuantity" },
        { title: "Total M3", value: "TotalM3" },
        { title: "Average Time (min)", value: "avgTime" },
        { title: "Total Weight", value: "TotalWeight" },
      ],

      detailsDialog: false,
      selectedUser: null,
      userOrders: [],
      detailHeaders: [
        { title: "Order", value: "Number" },
        { title: "Date", value: "Date" },
        { title: "Total Quantity", value: "TotalQuantity" },
        { title: "Total Weight", value: "TotalWeight" },
        { title: "Total M3", value: "TotalM3" },
        { title: "Minutes to Pack", value: "minutes_to_pack" },
      ],
    };
  },

  mounted() {
    this.performanceUsers();
  },
  computed: {
    aggregatedData() {
      const result = [];
      this.data.forEach((item) => {
        const existing = result.find(
          (r) => r.IDUzytkownika === item.IDUzytkownika
        );
        if (existing) {
          existing.TotalQuantity += parseFloat(item.TotalQuantity);
          existing.TotalWeight += parseFloat(item.TotalWeight);
          existing.TotalM3 += parseFloat(item.TotalM3);
          existing.totalMinutes =
            (existing.totalMinutes || existing.avgTime || 0) +
            (parseFloat(item.minutes_to_pack) || 0);
          existing.avgTime = (
            existing.totalMinutes /
            (existing.OrderCount + 1)
          ).toFixed(2);
          existing.OrderCount += 1;
        } else {
          result.push({
            ...item,
            TotalQuantity: parseFloat(item.TotalQuantity),
            TotalWeight: parseFloat(item.TotalWeight),
            TotalM3: parseFloat(item.TotalM3),
            avgTime: parseFloat(item.minutes_to_pack) || 0,
            OrderCount: 1,
          });
        }
      });
      return result;
    },
  },
  watch: {
    dateMin() {
      this.performanceUsers();
    },
    dateMax() {
      this.performanceUsers();
    },
    IDUser() {
      this.performanceUsers();
    },
  },
  methods: {
    moment,
    showDetails(event, row) {
      const userId = row.item.IDUzytkownika;

      this.selectedUser = userId;
      this.userOrders = this.data.filter(
        (order) => order.IDUzytkownika === this.selectedUser
      );
      this.detailsDialog = true;
    },
    getUserName(userId) {
      const user = this.users.find((u) => u.value === userId);
      return user ? user.title : "Unknown User";
    },
    performanceUsers() {
      this.users = [];
      this.data = [];
      this.loading = true;
      axios
        .post("/api/performanceUsers", {
          dateMin: moment(this.dateMin).format("YYYY-MM-DD"),
          dateMax: moment(this.dateMax).format("YYYY-MM-DD"),
          userId: this.IDUser.length ? this.IDUser : null,
        })
        .then((response) => {
          this.loading = false;
          // Handle the response data as needed
          this.data = response.data.data;

          // Convert users object to array of objects
          this.users = Object.entries(response.data.users).map(
            ([value, title]) => ({
              title,
              value: parseInt(value),
            })
          );
        })
        .catch((error) => {
          this.loading = false;
          console.error("Error fetching user performance:", error);
        });
    },
  },
};
</script>
