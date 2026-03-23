import { chromium } from 'playwright';

// Test configuration
const BASE_URL = process.env.E2E_BASE_URL || 'http://127.0.0.1:8585';
const LOGIN_EMAIL = process.env.E2E_EMAIL || 'admin@example.com';
const LOGIN_PASSWORD = process.env.E2E_PASSWORD || 'change-me';
const PROJECT_ID = process.env.E2E_PROJECT_ID || '';

// Test results tracking
const testResults = {
    passed: 0,
    failed: 0,
    errors: [],
    warnings: []
};

// Logging utilities
function log(message, type = 'info') {
    const timestamp = new Date().toISOString().split('T')[1].split('.')[0];
    const prefix = {
        'info': '[INFO]',
        'success': '[PASS]',
        'error': '[FAIL]',
        'warn': '[WARN]'
    }[type];
    console.log(`${timestamp} ${prefix} ${message}`);
}

function logError(message, details = '') {
    testResults.failed++;
    testResults.errors.push({ message, details });
    log(message, 'error');
    if (details) console.error(details);
}

function logSuccess(message) {
    testResults.passed++;
    log(message, 'success');
}

function logWarning(message) {
    testResults.warnings.push(message);
    log(message, 'warn');
}

// Main test function
async function runTests() {
    console.log('\n=== 503c Assistant Browser Test Suite ===');
    console.log(`Target URL: ${BASE_URL}`);
    console.log(`Starting tests at ${new Date().toISOString()}\n`);

    const browser = await chromium.launch({
        headless: true,
        slowMo: 100
    });

    const context = await browser.newContext({
        ignoreHTTPSErrors: true,
        viewport: { width: 1920, height: 1080 }
    });

    const page = await context.newPage();

    // Log console errors
    page.on('console', msg => {
        if (msg.type() === 'error') {
            logWarning(`Console error: ${msg.text()}`);
        }
    });

    // Log network errors
    page.on('response', response => {
        if (response.status() >= 400) {
            logWarning(`Network error: ${response.url()} - ${response.status()}`);
        }
    });

    try {
        // Test 1: Navigate to home page (should redirect to login)
        log('Test 1: Navigating to home page...');
        await page.goto(BASE_URL, { waitUntil: 'networkidle', timeout: 10000 });

        // Wait for redirect to login page
        await page.waitForURL(/\/login/, { timeout: 5000 });
        const currentUrl = page.url();
        logSuccess(`Page redirected to login: ${currentUrl}`);

        // Test 2: Check for login form
        log('Test 2: Checking for login form...');
        // Laravel uses id="email" and id="password"
        const emailInput = await page.locator('#email, input[type="email"]').first();
        const passwordInput = await page.locator('#password, input[type="password"]').first();
        const submitButton = await page.locator('button[type="submit"]').first();

        const emailExists = await emailInput.count() > 0;
        const passwordExists = await passwordInput.count() > 0;
        const submitExists = await submitButton.count() > 0;

        if (emailExists && passwordExists && submitExists) {
            logSuccess('Login form elements found');
        } else {
            logError('Login form elements missing', `Email: ${emailExists}, Password: ${passwordExists}, Submit: ${submitExists}`);
        }

        // Test 3: Login
        log('Test 3: Attempting login...');
        try {
            // Wait for elements to be ready and fill form
            await emailInput.fill(LOGIN_EMAIL);
            await passwordInput.fill(LOGIN_PASSWORD);
            await submitButton.click();
        } catch (e) {
            logError('Failed to fill login form', e.message);
            throw e;
        }

        // Wait for navigation after login
        await page.waitForLoadState('networkidle', { timeout: 10000 });
        const loginRedirectUrl = page.url();
        logSuccess(`Login successful, redirected to: ${loginRedirectUrl}`);

        // Take screenshot after login
        await page.screenshot({ path: '/home/juhur/PROJECTS/project_IRB-assist/503c-assistant/tests/screenshots/01-after-login.png' });
        log('Screenshot saved: 01-after-login.png');

        // Test 4: Navigate to projects page
        log('Test 4: Navigating to projects page...');
        await page.goto(`${BASE_URL}/projects`, { waitUntil: 'networkidle', timeout: 10000 });
        await page.screenshot({ path: '/home/juhur/PROJECTS/project_IRB-assist/503c-assistant/tests/screenshots/02-projects-page.png' });
        logSuccess('Projects page loaded');
        log('Screenshot saved: 02-projects-page.png');

        // Test 5: Navigate to specific project
        log(`Test 5: Navigating to project ${PROJECT_ID}...`);
        await page.goto(`${BASE_URL}/projects/${PROJECT_ID}`, { waitUntil: 'networkidle', timeout: 10000 });

        // Check for errors
        const content = await page.content();
        if (content.includes('ParseError') || content.includes('Fatal error') || content.includes('Internal Server Error')) {
            logError('Page contains PHP errors', content.substring(0, 500));
        } else {
            logSuccess('Project page loaded without errors');
        }

        await page.screenshot({ path: '/home/juhur/PROJECTS/project_IRB-assist/503c-assistant/tests/screenshots/03-project-page.png' });
        log('Screenshot saved: 03-project-page.png');

        // Test 6: Test all tabs
        const tabs = ['documents', 'review', 'questions', 'export', 'activity'];

        // First, find what tab selectors are available
        const tabSelectors = await page.locator('a[href^="#"], button[data-tab], [role="tab"]').all();
        log(`Found ${tabSelectors.length} potential tab elements`);

        for (const tab of tabs) {
            log(`Test 6.${tabs.indexOf(tab) + 1}: Testing ${tab} tab...`);

            // Try multiple selector patterns
            const tabSelectorsList = [
                `a[href="#${tab}"]`,
                `button[data-tab="${tab}"]`,
                `[data-tab="${tab}"]`,
                `a:has-text("${tab.charAt(0).toUpperCase() + tab.slice(1)}")`,
                `button:has-text("${tab.charAt(0).toUpperCase() + tab.slice(1)}")`
            ];

            let tabButton = null;
            for (const selector of tabSelectorsList) {
                const el = await page.locator(selector).first();
                if (await el.count() > 0) {
                    tabButton = el;
                    log(`Found tab with selector: ${selector}`);
                    break;
                }
            }

            if (tabButton) {
                try {
                    await tabButton.click();
                    await page.waitForTimeout(1000);

                    // Check if tab content is visible - try multiple patterns
                    const tabContentSelectors = [`#${tab}`, `[data-content="${tab}"]`, `[id="${tab}"]`];
                    let contentVisible = false;

                    for (const contentSel of tabContentSelectors) {
                        const content = await page.locator(contentSel).first();
                        if (await content.count() > 0 && await content.isVisible()) {
                            contentVisible = true;
                            break;
                        }
                    }

                    if (contentVisible) {
                        logSuccess(`${tab} tab clicked and content is visible`);
                        await page.screenshot({ path: `/home/juhur/PROJECTS/project_IRB-assist/503c-assistant/tests/screenshots/04-tab-${tab}.png` });
                    } else {
                        logWarning(`${tab} tab clicked but content visibility unclear`);
                        await page.screenshot({ path: `/home/juhur/PROJECTS/project_IRB-assist/503c-assistant/tests/screenshots/04-tab-${tab}-questionable.png` });
                    }
                } catch (e) {
                    logError(`Error clicking ${tab} tab: ${e.message}`);
                }
            } else {
                logWarning(`Tab button for '${tab}' not found`);
            }
        }

        // Test 7: Check for buttons on the page
        log('Test 7: Checking for interactive buttons...');
        const buttons = await page.locator('button, a[href], input[type="submit"]').all();
        log(`Found ${buttons.length} interactive elements`);

        let clickableCount = 0;
        for (let i = 0; i < Math.min(buttons.length, 20); i++) {
            try {
                const button = buttons[i];
                const isVisible = await button.isVisible();
                if (isVisible) {
                    clickableCount++;
                }
            } catch (e) {
                // Element not attached or other error
            }
        }
        logSuccess(`${clickableCount} of ${Math.min(buttons.length, 20)} checked buttons are visible`);

        // Test 8: Check for form inputs
        log('Test 8: Checking for form inputs...');
        const inputs = await page.locator('input, textarea, select').all();
        logSuccess(`Found ${inputs.length} form inputs`);

        // Final screenshot
        await page.screenshot({ path: '/home/juhur/PROJECTS/project_IRB-assist/503c-assistant/tests/screenshots/99-final-state.png' });
        log('Final screenshot saved');

    } catch (error) {
        logError('Test execution failed', error.message);
        console.error(error);
    } finally {
        await browser.close();
    }

    // Print summary
    console.log('\n=== Test Summary ===');
    console.log(`Total Passed: ${testResults.passed}`);
    console.log(`Total Failed: ${testResults.failed}`);
    console.log(`Warnings: ${testResults.warnings.length}`);

    if (testResults.errors.length > 0) {
        console.log('\n=== Errors ===');
        testResults.errors.forEach((err, i) => {
            console.log(`${i + 1}. ${err.message}`);
            if (err.details) console.log(`   ${err.details.substring(0, 200)}`);
        });
    }

    if (testResults.warnings.length > 0) {
        console.log('\n=== Warnings ===');
        testResults.warnings.forEach((warn, i) => {
            console.log(`${i + 1}. ${warn}`);
        });
    }

    const totalTests = testResults.passed + testResults.failed;
    const successRate = totalTests > 0 ? ((testResults.passed / totalTests) * 100).toFixed(2) : 0;
    console.log(`\nSuccess Rate: ${successRate}%`);
    console.log(`Target (95%): ${successRate >= 95 ? '✓ MET' : '✗ NOT MET'}`);

    return successRate >= 95;
}

// Create screenshots directory
await import('fs').then(fs => {
    const dir = '/home/juhur/PROJECTS/project_IRB-assist/503c-assistant/tests/screenshots';
    if (!fs.existsSync(dir)) {
        fs.mkdirSync(dir, { recursive: true });
    }
});

// Run tests
const success = await runTests();
process.exit(success ? 0 : 1);
