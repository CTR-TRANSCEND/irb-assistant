import { chromium } from 'playwright';
import path from 'path';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));

const BASE_URL = 'http://localhost:8000';
const EMAIL = 'admin@example.com';
const PASSWORD = 'change-me';
const SCREENSHOTS_DIR = path.join(__dirname, '..', 'screenshots');
const PROJECT_NAME = 'COVID-19 Vaccine Efficacy Study';

async function run() {
    const browser = await chromium.launch({ headless: true });
    const context = await browser.newContext({
        viewport: { width: 1280, height: 800 },
    });
    const page = await context.newPage();

    // 1. Login page screenshot
    console.log('Capturing 01-login.png ...');
    await page.goto(`${BASE_URL}/login`);
    await page.waitForLoadState('networkidle');
    await page.screenshot({ path: `${SCREENSHOTS_DIR}/01-login.png`, fullPage: false });
    console.log('  Saved 01-login.png');

    // 2. Login with credentials
    console.log('Logging in ...');
    await page.fill('input[name="email"]', EMAIL);
    await page.fill('input[name="password"]', PASSWORD);
    await page.click('button[type="submit"]');
    await page.waitForLoadState('networkidle');
    console.log(`  Current URL after login: ${page.url()}`);

    // 3. Projects dashboard - create test project if needed
    console.log('Navigating to projects dashboard ...');
    await page.goto(`${BASE_URL}/projects`);
    await page.waitForLoadState('networkidle');

    // Check if the test project already exists
    const projectExists = await page.locator(`text=${PROJECT_NAME}`).count();
    let projectUrl = null;

    if (projectExists === 0) {
        console.log(`  Creating project: ${PROJECT_NAME}`);

        // Look for a create/new project button or form
        const createBtn = page.locator('a, button').filter({ hasText: /new project|create project|add project/i }).first();
        const createBtnCount = await createBtn.count();

        if (createBtnCount > 0) {
            await createBtn.click();
            await page.waitForLoadState('networkidle');
        }

        // Fill in the project name - try common form field patterns
        const nameInput = page.locator('input[name="title"], input[name="name"], input[id="title"], input[id="name"]').first();
        const nameInputCount = await nameInput.count();

        if (nameInputCount > 0) {
            await nameInput.fill(PROJECT_NAME);
            await page.locator('button[type="submit"], input[type="submit"]').first().click();
            await page.waitForLoadState('networkidle');
            console.log(`  Project created. URL: ${page.url()}`);
            projectUrl = page.url();
        } else {
            // Try submitting via form if there's a modal/inline form
            const form = page.locator('form').first();
            const formCount = await form.count();
            if (formCount > 0) {
                const inputs = await form.locator('input[type="text"], input[type="email"], textarea').all();
                if (inputs.length > 0) {
                    await inputs[0].fill(PROJECT_NAME);
                    await form.locator('button[type="submit"]').first().click();
                    await page.waitForLoadState('networkidle');
                    projectUrl = page.url();
                }
            }
        }

        // Go back to projects to take dashboard screenshot
        await page.goto(`${BASE_URL}/projects`);
        await page.waitForLoadState('networkidle');
    } else {
        console.log(`  Project "${PROJECT_NAME}" already exists`);
    }

    console.log('Capturing 02-projects-dashboard.png ...');
    await page.screenshot({ path: `${SCREENSHOTS_DIR}/02-projects-dashboard.png`, fullPage: false });
    console.log('  Saved 02-projects-dashboard.png');

    // 4. Navigate into a project
    // Find the COVID-19 project link or the first project link
    let targetProjectLink = page.locator(`a:has-text("${PROJECT_NAME}")`).first();
    let targetCount = await targetProjectLink.count();

    if (targetCount === 0) {
        // Fall back to first project link on the page
        targetProjectLink = page.locator('a[href*="/projects/"]').first();
        targetCount = await targetProjectLink.count();
    }

    if (targetCount > 0) {
        const href = await targetProjectLink.getAttribute('href');
        console.log(`  Navigating to project: ${href}`);
        await targetProjectLink.click();
        await page.waitForLoadState('networkidle');
        projectUrl = page.url();
        console.log(`  Project URL: ${projectUrl}`);
    } else if (projectUrl) {
        await page.goto(projectUrl);
        await page.waitForLoadState('networkidle');
    } else {
        console.warn('  Could not find a project to navigate to');
    }

    // 5. Project documents tab
    console.log('Navigating to documents tab ...');
    // Look for tabs/links labeled Documents
    const docsTab = page.locator('a, button, [role="tab"]').filter({ hasText: /documents?/i }).first();
    const docsTabCount = await docsTab.count();

    if (docsTabCount > 0) {
        await docsTab.click();
        await page.waitForLoadState('networkidle');
    } else {
        // Try URL-based navigation with tab query params
        const currentUrl = page.url();
        await page.goto(`${currentUrl}?tab=documents`);
        await page.waitForLoadState('networkidle');
    }

    console.log('Capturing 03-project-documents.png ...');
    await page.screenshot({ path: `${SCREENSHOTS_DIR}/03-project-documents.png`, fullPage: false });
    console.log('  Saved 03-project-documents.png');

    // 6. Project review tab
    console.log('Navigating to review tab ...');
    const reviewTab = page.locator('a, button, [role="tab"]').filter({ hasText: /review|fields|analysis/i }).first();
    const reviewTabCount = await reviewTab.count();

    if (reviewTabCount > 0) {
        await reviewTab.click();
        await page.waitForLoadState('networkidle');
    } else {
        const currentUrl = page.url().split('?')[0];
        await page.goto(`${currentUrl}?tab=review`);
        await page.waitForLoadState('networkidle');
    }

    console.log('Capturing 04-project-review.png ...');
    await page.screenshot({ path: `${SCREENSHOTS_DIR}/04-project-review.png`, fullPage: false });
    console.log('  Saved 04-project-review.png');

    // 7. Admin panel
    console.log('Navigating to admin panel ...');
    await page.goto(`${BASE_URL}/admin`);
    await page.waitForLoadState('networkidle');
    console.log(`  Admin URL: ${page.url()}`);

    console.log('Capturing 05-admin-panel.png ...');
    await page.screenshot({ path: `${SCREENSHOTS_DIR}/05-admin-panel.png`, fullPage: false });
    console.log('  Saved 05-admin-panel.png');

    await browser.close();
    console.log('\nAll screenshots captured successfully.');
    console.log(`Screenshots saved to: ${SCREENSHOTS_DIR}`);
}

run().catch(err => {
    console.error('Screenshot capture failed:', err);
    process.exit(1);
});
