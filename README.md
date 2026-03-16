# short-video

Local MVP for discovering X video tweets and resolving playable MP4 URLs with Playwright.
The frontend is a static vanilla HTML/JS app styled with Tailwind CSS.

## Quick start

1. Install dependencies:

   ```bash
   npm install
   npm run playwright:install
   ```

2. Configure source accounts in `config/sources.json`.

3. Create a persistent X login session:

   ```bash
   npm run auth
   ```

   If you already have a logged-in Playwright-controlled browser session, export its storage
   state to `data/x-storage-state.json` and both discovery and resolution will reuse it
   automatically.

4. Start the app:

   ```bash
   npm run dev
   ```

   Tailwind styles are rebuilt automatically before the local server starts.

5. Trigger a manual crawl if needed:

   ```bash
   npm run crawl:once
   ```

## Commands

- `npm run dev` starts the local API, static frontend, and scheduler.
- `npm run build:styles` rebuilds the Tailwind CSS bundle in `public/styles.css`.
- `npm run dev:styles` watches Tailwind sources while editing the UI.
- `npm run crawl:once` runs a one-shot discovery and resolution cycle.
- `npm run resolve:pending` only resolves pending tweets with Playwright.
- `npm run auth` opens a persistent browser profile for X login.
- `npm test` runs the project test suite.
