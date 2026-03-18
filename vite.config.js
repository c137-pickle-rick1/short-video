import { defineConfig } from "vite";
import laravel from "laravel-vite-plugin";

export default defineConfig({
  plugins: [
    laravel({
      input: [
        "laravel/resources/shortvideo/styles.css",
        "laravel/resources/js/pages/feed/index.js",
        "laravel/resources/js/pages/subscriptions/index.js",
        "laravel/resources/js/pages/bookmarks/index.js",
        "laravel/resources/js/pages/history/index.js",
        "laravel/resources/js/pages/interactions/index.js",
        "laravel/resources/js/pages/auth/modal.js",
        "laravel/resources/js/pages/auth/login.js",
        "laravel/resources/js/pages/layout/header-language-menu.js",
        "laravel/resources/js/features/social/follow-buttons.js",
        "laravel/resources/js/features/profile/editor.js",
        "laravel/resources/js/features/profile/social-modal.js",
        "laravel/resources/js/features/profile/video-upload.js"
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
