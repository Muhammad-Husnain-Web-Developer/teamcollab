# Screens 01 — Authentication

Five screens, all on `AuthLayout`.

## Shared layout — `AuthLayout`

A **split screen**, dark background (`dark-800`) with three blurred colour
"mesh blobs" floating behind everything.

```
┌────────────────────────────┬───────────────────────┐
│  MARKETING PANEL           │  FORM PANEL           │
│  hidden below 1024px       │  always visible       │
│  50% (60% on xl)           │                       │
│                            │                       │
│  ● TeamCollab              │  <form slot>          │
│    COLLABORATION           │                       │
│                            │                       │
│  Big gradient headline     │                       │
│  Sub-paragraph             │                       │
│  ✓ feature bullets         │                       │
│                            │                       │
│  ── Trusted by teams at ── │                       │
│  [faded logo row]          │                       │
└────────────────────────────┴───────────────────────┘
```

- Logo mark: 40×40 `rounded-xl`, `brand-500` fill, white glyph, brand glow shadow.
- Headline: 36–48px bold, with a span in a `brand-400 → purple-400` gradient.
- Below 1024px the marketing half disappears and the form is full width.

## 01a — Login

- Heading: **"Welcome back"** / sub: "Sign in to your workspace".
- On a workspace subdomain it becomes **"Sign in to {Workspace Name}"** with
  "Enter your credentials to access the workspace" — *design both variants.*
- Fields: Email (`you@company.com`), Password (`••••••••`) with a
  **show/hide eye toggle**.
- "Forgot password?" link sits inline, right-aligned above the password field.
- Inline field errors in `red-400`, plus a red border on the input.
- Divider: "or continue with" — **social buttons are not implemented.**
- Footer link to Register.

## 01b — Register

- Fields: Full name (`John Doe`), Work email, Password (`Min. 8 characters`),
  Confirm password — both password fields have eye toggles.
- **Live match feedback**: "Passwords match" (positive) / "Passwords do not
  match" (negative) as the user types. Design both.
- Legal line with "Terms of Service" and "Privacy Policy" links.

## 01c — Forgot Password

- Heading **"Reset your password"**, one email field, submit.
- Needs a **success state** ("check your inbox") — design it.

## 01d — Reset Password

- Heading **"Set new password"**, sub: "Choose a strong, unique password."
- Email (usually pre-filled/read-only), New password, Confirm new password.

## 01e — Verify Email

- Heading **"Check your email"**.
- Confirmation line: "A new verification link has been sent to your email."
- Has a resend action and a sign-out link.

## Form control spec (current)

- `.input-field` — `dark-700` fill, `dark-600` border, `rounded-xl`,
  ~10px vertical padding, `text-sm`, brand focus ring.
- Labels: `text-sm font-medium`, `dark-200`, 6px below.
- Error text: `text-xs`, `red-400`, 6px above.
- Buttons: full width, `brand-500`, `rounded-xl`, `font-medium`.
- Disabled/submitting state: reduced opacity + spinner.
