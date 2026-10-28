/** @type {import('tailwindcss').Config} */
module.exports = {
  // Every file that emits utility classes must be listed, or its classes are silently dropped
  // from assets/app.css and the page renders unstyled. reports-view.php and reminder.php were
  // missing, which is why the Reports view had no card spacing, letter-spacing or tabular digits.
  content: ['./index.php', './login.php', './reports-view.php', './teams-view.php', './reminder.php'],
  theme: { extend: {} },
  plugins: [],
}
