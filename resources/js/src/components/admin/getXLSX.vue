<template>
	<v-container>
		<v-row>
			<v-col
				md="6"
				sm="12"
			>
				<v-select
					label="Magazyn"
					v-model="IDWarehouse"
					:items="warehouses"
					item-title="Nazwa"
					item-value="IDMagazynu"
					@change="clear()"
					hide-details="auto"
				></v-select>
			</v-col>
			<v-col
				md="6"
				sm="12"
				><v-select
					v-model="selectedMonth"
					:items="month"
					item-title="state"
					item-value="abbr"
					label="Select"
					persistent-hint
					return-object
					single-line
				></v-select
			></v-col>
		</v-row>
	</v-container>
</template>
    <script>
    export default {
      name: 'FulstorGetXLSX',

      data() {
        return {
          month : [
{id:0,name:'styczeń'},
{id:1,name:'luty'},
{id:2,name:'marzec'},
{id:3,name:'kwiecień'},
{id:4,name:'maj'},
{id:5,name:'czerwiec'},
{id:6,name:'lipiec'},
{id:7,name:'sierpień'},
{id:8,name:'wrzesień'},
{id:9,name:'październik'},
{id:10,name:'listopad'},
{id:11,name:'grudzień'}
]
        };
      },

      mounted() {
        this.month = this.month.filter(m=>{return m.id <= })
      },

      methods: {

      },
    };
    </script>
