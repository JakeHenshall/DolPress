import { defineConfig } from "@playwright/test";

export default defineConfig({
  testDir: "./tests/e2e",
  reporter: "list",
  timeout: 30_000,
  expect: { timeout: 10_000 },
  projects: [ { name: "chromium", use: { browserName: "chromium" } } ],
  use: {
    baseURL: process.env.PLAYWRIGHT_BASE_URL,
    screenshot: "only-on-failure",
    trace: "retain-on-failure",
  },
});
