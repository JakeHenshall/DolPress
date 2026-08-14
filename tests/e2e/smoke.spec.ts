import { test, expect } from "@playwright/test";
import AxeBuilder from "@axe-core/playwright";

const base = process.env.PLAYWRIGHT_BASE_URL;
const postId = process.env.DOLPRESS_POST_ID;
const username = process.env.WP_ADMIN_USER || "admin";
const password = process.env.WP_ADMIN_PASSWORD || "password";

test.skip( ! base || ! postId, "Set PLAYWRIGHT_BASE_URL and DOLPRESS_POST_ID to run WordPress end-to-end tests." );

test.beforeEach( async ( { page } ) => {
	await page.goto( `${base}/wp-login.php` );
	await page.locator( "#user_login" ).fill( username );
	await page.locator( "#user_pass" ).fill( password );
	await Promise.all( [ page.waitForURL( /wp-admin/ ), page.locator( "#wp-submit" ).click() ] );
} );

test( "editor is keyboard operable and has no detectable accessibility violations", async ( { page } ) => {
	await page.goto( `${base}/wp-admin/post.php?post=${postId}&action=edit` );
	const editor = page.locator( ".dp-shell" );
	await expect( editor ).toBeVisible();

	const opener = page.locator( "[data-action=palette]" );
	await opener.focus();
	await opener.press( "Enter" );
	await expect( page.locator( ".dp-modal__panel" ) ).toBeVisible();
	await page.keyboard.press( "Escape" );
	await expect( opener ).toBeFocused();

	const results = await new AxeBuilder( { page } ).include( ".dp-shell" ).analyze();
	expect( results.violations ).toEqual( [] );
} );

test( "autosave uses the revisions endpoint and never unpublishes the post", async ( { page } ) => {
	await page.goto( `${base}/wp-admin/post.php?post=${postId}&action=edit` );
	const autosave = page.waitForRequest(
		( request ) => request.method() === "POST" && decodeURIComponent( request.url() ).includes( `/posts/${postId}/autosaves` ),
		{ timeout: 10_000 }
	);
	await page.locator( "#dolpress-source" ).fill( '$TX,"Autosave remains published"$' );
	const request = await autosave;
	const body = request.postDataJSON() as Record<string, unknown>;
	expect( body.status ).toBeUndefined();

	await page.goto( `${base}/?p=${postId}` );
	await expect( page.locator( ".dolpress-document" ) ).toBeVisible();
} );

test( "published DolPress source renders on the frontend", async ( { page } ) => {
	await page.goto( `${base}/?p=${postId}` );
	await expect( page.locator( ".dolpress-document" ) ).toContainText( "DolPress launch verification" );
} );

test( "unrelated pages do not load DolPress frontend assets", async ( { page } ) => {
	const dolpressAssets: string[] = [];
	page.on( "request", ( request ) => {
		if ( request.url().includes( "/plugins/dolpress/assets/dist/frontend" ) ) dolpressAssets.push( request.url() );
	} );
	await page.goto( `${base}/` );
	expect( dolpressAssets ).toEqual( [] );
} );
