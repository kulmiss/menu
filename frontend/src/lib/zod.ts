import { z } from 'zod'

// The only place the app imports zod (eslint blocks the rest). "jitless" stops
// zod probing `new Function("")` on first use: the page's Content-Security-Policy
// blocks eval, and Chrome reports that blocked probe as a CSP violation in the
// console on every form page, even though zod catches it.
z.config({ jitless: true })

export { z }
