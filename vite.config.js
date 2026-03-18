import { defineConfig } from "vite";
import laravel from "laravel-vite-plugin";

export default defineConfig({
  plugins: [
    laravel({
      input: [
        "laravel/resources/shortvideo/styles.css",
        "laravel/resources/js/app.js",
        "laravel/resources/js/authModal.js",
        "laravel/resources/js/socialGraph.js",
        "laravel/resources/js/app/headerLanguageMenu.js",
        "laravel/resources/js/app/profileEditor.js",
        "laravel/resources/js/app/profileSocialModal.js",
        "laravel/resources/js/app/profileVideoUpload.js"
      ],
      publicDirectory: "laravel/public",
      buildDirectory: "build",
      refresh: [
        "laravel/app/**",
        "laravel/resources/views/**",
        "laravel/resources/shortvideo/**",
        "laravel/routes/**"
      ]
    })
  ],
  server: {
    host: "127.0.0.1",
    port: 5173,
    strictPort: true
  },
  build: {
    outDir: "laravel/public/build",
    emptyOutDir: true
  }
});
