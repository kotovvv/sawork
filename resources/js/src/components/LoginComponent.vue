<template>
  <v-card class="mx-auto my-8" elevation="16" max-width="344">
    <v-card-item>
      <v-card-title> Podłącz do systemu </v-card-title>
      <v-card-subtitle class="red">{{ message }} </v-card-subtitle>
    </v-card-item>

    <form>
      <v-card-text>
        <v-form ref="form">
          <v-text-field
            label="Login"
            name="login"
            type="text"
            v-model="login"
            :rules="userNameRequired"
            required
            @keyup.enter="onSubmit"
            prepend-icon="mdi-account-outline"
          >
          </v-text-field>

          <v-text-field
            id="password"
            label="Password"
            name="password"
            :type="showPassword ? 'text' : 'password'"
            v-model="password"
            :append-icon="showPassword ? 'mdi-eye' : 'mdi-eye-off'"
            @click:append="showPassword = !showPassword"
            :rules="passwordRequired"
            required
            @keyup.enter="onSubmit"
            prepend-icon="mdi-key"
          >
          </v-text-field>
        </v-form>
      </v-card-text>
      <v-card-actions>
        <v-btn color="primary" @click="onSubmit" variant="outlined" block
          >Logowanie</v-btn
        >
      </v-card-actions>
    </form>
  </v-card>
</template>

<script>
export default {
  data() {
    return {
      login: "",
      password: "",
      errors: {},
      showPassword: false,
      userNameRequired: [(v) => !!v || "without login?"],
      passwordRequired: [(v) => !!v || "Password?"],
      message: "",
    };
  },
  methods: {
    onSubmit() {
      this.$emit("login", { login: this.login, password: this.password });
    },
  },
};
</script>
