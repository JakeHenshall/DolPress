import { test, expect } from "@playwright/test";

const base = process.env.PLAYWRIGHT_BASE_URL;

test.skip( ! base, "Set PLAYWRIGHT_BASE_URL to run WordPress end-to-end tests." );

test( "login page is reachable", async ( { page } ) => {
	await page.goto( `${base}/wp-login.php` );
	await expect( page.locator( "#loginform" ) ).toBeVisible();
} );
