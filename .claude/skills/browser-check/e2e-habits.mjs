// Drives the Streakly SPA in real Chrome through the Phase 5 habit flow.
// Needs playwright-core (installed next to this script, see SKILL.md) and both dev servers running.
// Env: BASE_URL (default http://localhost:5173), CHROME_PATH, SHOTS_DIR (default: current directory).
import { chromium } from 'playwright-core'
import { join } from 'node:path'

const BASE = process.env.BASE_URL ?? 'http://localhost:5173'
const CHROME_PATH =
  process.env.CHROME_PATH ?? 'C:/Program Files/Google/Chrome/Application/chrome.exe'
const SHOTS_DIR = process.env.SHOTS_DIR ?? process.cwd()
const PASSWORD = 'password-12345'
const runId = Date.now()

const results = []
const check = (name, isOk, detail = '') => {
  results.push({ name, isOk })
  console.log(`${isOk ? 'PASS' : 'FAIL'} ${name} ${detail}`)
}
const shot = (page, name) => page.screenshot({ path: join(SHOTS_DIR, `${name}.png`) })

const browser = await chromium.launch({ executablePath: CHROME_PATH })
const consoleErrors = []

async function openUser(label) {
  const page = await (await browser.newContext()).newPage()
  page.on('console', (message) => message.type() === 'error' && consoleErrors.push(`${label}: ${message.text()}`))
  page.on('pageerror', (error) => consoleErrors.push(`${label}: ${String(error)}`))
  return page
}

async function register(page, label) {
  await page.goto(BASE + '/register')
  await page.fill('#name', `E2E ${label}`)
  await page.fill('#email', `e2e-habits-${label}-${runId}@example.com`)
  await page.fill('#password', PASSWORD)
  await page.fill('#password_confirmation', PASSWORD)
  await page.click('button[type=submit]')
  await page.waitForURL(BASE + '/')
  await page.waitForSelector('text=Your habits')
}

const cardFor = (page, name) => page.locator('li', { has: page.locator(`h2:text-is("${name}")`) })

// --- User A ---
const pageA = await openUser('A')
await register(pageA, 'a')
await pageA.waitForSelector('text=No habits yet')
check('new user sees the empty state', true)

// A whitespace-only name passes the browser's required check and fails server validation.
await pageA.fill('input[name=name]', '   ')
await pageA.click('text=Add habit')
await pageA.waitForSelector('p.text-red-600')
check('invalid habit shows a field error', true, await pageA.textContent('p.text-red-600'))

await pageA.fill('input[name=name]', 'Read')
await pageA.fill('textarea[name=description]', 'Twenty pages')
await pageA.click('text=Add habit')
await cardFor(pageA, 'Read').waitFor()
check('created habit appears in the list', (await cardFor(pageA, 'Read').textContent()).includes('Twenty pages'))
check('new habit starts at 0 days', (await cardFor(pageA, 'Read').textContent()).includes('0 days streak'))
check('create form is reset after saving', (await pageA.inputValue('input[name=name]')) === '')
await shot(pageA, 'habits-created')

// Edit inline.
await cardFor(pageA, 'Read').getByRole('button', { name: 'Edit' }).click()
await pageA.locator('li input[name=name]').fill('Read more')
await pageA.locator('li').getByRole('button', { name: 'Save' }).click()
await cardFor(pageA, 'Read more').waitFor()
check('edited name is shown', true)

// Mark and unmark today.
await cardFor(pageA, 'Read more').getByRole('button', { name: /Done today/ }).click()
await cardFor(pageA, 'Read more').getByText('1 day streak').waitFor()
check('marking today sets the streak to 1 day', true)
check(
  'done button shows the pressed state',
  (await cardFor(pageA, 'Read more').getByRole('button', { name: /Done today/ }).getAttribute('aria-pressed')) === 'true',
)
await shot(pageA, 'habits-done')

await pageA.reload()
await cardFor(pageA, 'Read more').getByText('1 day streak').waitFor()
check('streak survives a reload', true)

await cardFor(pageA, 'Read more').getByRole('button', { name: /Done today/ }).click()
await cardFor(pageA, 'Read more').getByText('0 days streak').waitFor()
check('unmarking today resets the streak to 0 days', true)

// Delete asks for confirmation; dismissing keeps the habit.
pageA.once('dialog', (dialog) => dialog.dismiss())
await cardFor(pageA, 'Read more').getByRole('button', { name: 'Delete' }).click()
check('cancelling the confirm keeps the habit', (await cardFor(pageA, 'Read more').count()) === 1)

pageA.once('dialog', (dialog) => dialog.accept())
await cardFor(pageA, 'Read more').getByRole('button', { name: 'Delete' }).click()
await pageA.waitForSelector('text=No habits yet')
check('deleted habit disappears', (await cardFor(pageA, 'Read more').count()) === 0)

// Leave one habit behind so user B has something not to see.
await pageA.fill('input[name=name]', 'Private run')
await pageA.click('text=Add habit')
await cardFor(pageA, 'Private run').waitFor()

// --- User B (separate browser context) ---
const pageB = await openUser('B')
await register(pageB, 'b')
await pageB.waitForSelector('text=No habits yet')
check("second user does not see the first user's habits", (await cardFor(pageB, 'Private run').count()) === 0)
await shot(pageB, 'habits-second-user')

await pageA.click('text=Log out')
await pageA.waitForURL('**/login')
check('logout returns to /login', pageA.url().endsWith('/login'))

console.log('console errors (422 and 401 responses are expected):', consoleErrors)
await browser.close()
process.exit(results.every((result) => result.isOk) ? 0 : 1)
