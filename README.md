# GBR Admin

Admin page for the Global Burgee Register. Served by GitHub Pages at
https://apps.garcez.net/gbr-admin/ (source: `main`, root).

- `admin.html` — the whole admin tool (single page). Log in with a GitHub
  token that has write access to the `gbr-data` repo. Edits club data in
  `gbr-data`, and the **Deploy** button runs its `build_and_deploy.yml`
  workflow on `main`.
- `server/` — the confirmation-email sender, which runs on the
  burgeeregister.org hosting (not on GitHub).

## Confirmation emails

When a submission is accepted, `admin.html` POSTs the club name, contact
email, first name and the GitHub token to
`https://burgeeregister.org/gbr_mail/send-confirmation.php`.

That script:
1. Checks the token against the GitHub API (it must be able to see and write
   to `gbr-data`).
2. Sends the fixed "submission accepted" email through Resend, using the key
   stored in `config.php` on the server.

The Resend key is never in this repo or in the admin page.

## Setting up the email sender

1. On the hosting, create `/public_html/gbr_mail/`.
2. Upload `server/send-confirmation.php` and `server/.htaccess` into it.
3. Copy `server/config.example.php` to `config.php`, replace
   `YOUR_NEW_RESEND_API_KEY` with the real key, and upload it to the same
   folder. Do not commit `config.php` (it is in `.gitignore`).
4. Test: accept a test club with your own email address.

## Rotating the Resend key

1. In Resend, create a new API key (sending access for burgeeregister.org).
2. Edit `config.php` on the hosting and replace the key.
3. Test with a club using your own email address.
4. Delete the old key in Resend.

## Settings tied to web addresses

- `$ALLOWED_ORIGIN` in `server/send-confirmation.php` must match the address
  the admin page is served from (currently `https://apps.garcez.net`). If the
  page moves, change it and re-upload the file.
- `GH_OWNER` / `GH_REPO` in `admin.html` point at the `gbr-data` repo.
  GitHub currently redirects the old owner name; the email script follows
  the redirect.

## Troubleshooting

Open the browser console (F12) on the admin page and look for
`Email failed: ...`. The message says whether the problem is CORS/origin,
the GitHub token check, or Resend rejecting the send. Also check the Resend
dashboard Logs: if nothing appears there, the request never reached Resend.
