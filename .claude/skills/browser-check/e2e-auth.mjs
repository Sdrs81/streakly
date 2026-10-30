// Drives the Streakly SPA in real Chrome through the Phase 4 auth flow.
// Needs playwright-core (installed next to this script, see SKILL.md) and both dev servers running.
// Env: BASE_URL (default http://localhost:5173), CHROME_PATH, SHOTS_DIR (default: current directory).
import { chromium } from 'playwright-core'
import { join } from 'node:path'

const BASE = process.env.BASE_URL ?? 'http://localhost:5173'
const CHROME_PATH =
  process.env.CHROME_PATH ?? 'C:/Program Files/Google/Chrome/Application/chrome.exe'
const SHOTS_DIR = process.env.SHOTS_DIR ?? process.cwd()
const PASSWORD = 'password-12345'
const email = `e2e-${Date.now()}@example.com`

const results = []
const check = (name, isOk, detail = '') => {
  results.push({ name, isOk })
  console.log(`${isOk ? 'PASS' : 'FAIL'} ${name} ${detail}`)
}
const shot = (page, name) => page.screenshot({ path: join(SHOTS_DIR, `${name}.png`) })
const storedToken = (page) => page.evaluate(() => localStorage.getItem('streakly_token'))

const browser = await chromium.launch({ executablePath: CHROME_PATH })
const page = await (await browser.newContext()).newPage()
const consoleErrors = []
page.on('console', (message) => message.type() === 'error' && consoleErrors.push(message.text()))
page.on('pageerror', (error) => consoleErrors.push(String(error)))

// A guest is sent to /login.
await page.goto(BASE + '/')
await page.waitForURL('**/login')
check('guest at / is redirected to /login', page.url().endsWith('/login'))
await shot(page, 'login')

// Wrong credentials give a 422 field error.
await page.fill('#email', 'nobody@example.com')
await page.fill('#password', 'wrong-password')
await page.click('button[type=submit]')
await page.waitForSelector('p.text-red-600')
check('bad login shows a field error', (await page.textContent('p.text-red-600')).length > 0)

// A mismatched password confirmation gives a 422 field error.
await page.click('text=Register')
await page.waitForURL('**/register')
await page.fill('#name', 'E2E User')
await page.fill('#email', email)
await page.fill('#password', PASSWORD)
await page.fill('#password_confirmation', 'different-12345')
await page.click('button[type=submit]')
await page.waitForSelector('p.text-red-600')
check('register mismatch shows a field error', true, await page.textContent('p.text-red-600'))
await shot(page, 'register-error')

// A valid registration lands on / with a stored token.
await page.fill('#password_confirmation', PASSWORD)
await page.click('button[type=submit]')
await page.waitForURL(BASE + '/')
await page.waitForSelector('text=Your habits')
check('register lands on /', page.url() === BASE + '/')
const oldToken = await storedToken(page)
check('token is stored in localStorage', Boolean(oldToken))
await shot(page, 'habits')

await page.reload()
await page.waitForSelector('text=Your habits')
check('refresh keeps the session', page.url() === BASE + '/')

await page.goto(BASE + '/login')
await page.waitForURL(BASE + '/')
check('logged-in user is redirected away from /login', page.url() === BASE + '/')

// Logout clears the token, and the old token is rejected by the API.
await page.click('text=Log out')
await page.waitForURL('**/login')
check('logout returns to /login', page.url().endsWith('/login'))
check('token is cleared after logout', (await storedToken(page)) === null)
const oldTokenStatus = await page.evaluate(
  async (token) =>
    (
      await fetch('/api/user', {
        headers: { Accept: 'application/json', Authorization: `Bearer ${token}` },
      })
    ).status,
  oldToken,
)
check('old token gets 401', oldTokenStatus === 401, String(oldTokenStatus))

await page.fill('#email', email)
await page.fill('#password', PASSWORD)
await page.click('button[type=submit]')
await page.waitForURL(BASE + '/')
check('login with the registered account works', page.url() === BASE + '/')

// A rejected stored token is cleared by the 401 handler.
await page.evaluate(() => localStorage.setItem('streakly_token', 'garbage'))
await page.reload()
await page.waitForURL('**/login')
check('invalid stored token redirects to /login', page.url().endsWith('/login'))
check('invalid stored token is cleared', (await storedToken(page)) === null)

console.log('console errors (422 and 401 responses are expected):', consoleErrors)
await browser.close()
process.exit(results.every((result) => result.isOk) ? 0 : 1)
