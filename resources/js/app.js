/**
 * First we will load all of this project's JavaScript dependencies which
 * includes Vue and other libraries. It is a great starting point when
 * building robust, powerful web applications using Vue and Laravel.
 */

require("./bootstrap");
import Vue from "vue";
import router from "./router";
import store from "./store";
import App from "./App.vue";
import Vuetify from "vuetify";
import colors from "vuetify/lib/util/colors";
import "bootstrap";
import VueChatScroll from "vue-chat-scroll";
import BaseLoading from "./components/ui/BaseLoading";
import '@mdi/font/css/materialdesignicons.css'
Vue.use(Vuetify);
Vue.use(VueChatScroll);
Vue.component("base-loading", BaseLoading);
const app = new Vue({
    router,
    store,
    vuetify: new Vuetify({
        icons: {
            iconfont: "mdi",
        },
        theme: {
            dark: true,
            themes: {
                light: {
                    primary: colors.lightBlue,
                    secondary: colors.grey.darken1,
                    accent: colors.pink.darken1,
                    error: colors.red.accent3,
                    background: colors.indigo.lighten5,
                    info: colors.teal.darken1,
                },
                dark: {
                    primary: colors.blue.darken4,
                    background: colors.indigo.base,
                    info: colors.teal.lighten1,
                },
            },
        },
    }),
    render: (h) => h(App),
}).$mount("#app");