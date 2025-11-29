# Error Logger — WordPress Plugin

**Error Logger** is a lightweight WordPress plugin that watches for **failed Elementor Pro form submissions** and sends detailed error alerts to **Slack**. This helps site owners quickly detect issues such as broken integrations, API failures, webhook problems, and unexpected form processing errors.

---

## ⭐ Features

- Detects **failed Elementor Pro form submission actions**
- Sends structured alerts directly to **Slack**
- Includes user agent, timestamp, page URL, and error details
- Supports **global field filtering** for cleaner Slack messages
- Optional **per-form field filtering** for deeper control
- Auto-prevents duplicate notifications
- Null impact on site performance (runs on shutdown in the background)
- No external dependencies

---

## 🚀 How It Works

1. A visitor submits a form using **Elementor Pro forms**.
2. Elementor logs submission actions (email sending, webhook, CRM integrations, etc.).
3. If an action fails:
   - The plugin scans the recent log record
   - Extracts key information
   - Sends a structured alert to Slack
4. You get notified instantly with details necessary to diagnose the issue.

---

## 🛠️ Setup

1. Install and activate the plugin.
2. Go to  
   **WP Admin → Settings → Error Logger**
3. Enter your **Slack Incoming Webhook URL**.
4. Choose which fields you want included in Slack notifications:
   - **Global fields** (applies to all forms)
   - **Per-form fields** (optional — overrides global)
5. Save your settings.

That’s it. Errors will now appear in Slack automatically.

---

## 🧪 Testing

Trigger any intentional failure in an Elementor form action to verify Slack notifications:
- Temporary invalid webhook URL in Elementor action  
- Disabled integration  
- Wrong API key, etc.

Slack should immediately receive a formatted alert.

---

## 🧹 Cleanup Tools (BETA)

The settings page includes:
- **Remove Stale Form Entries**  
  Removes form configurations for forms that no longer exist in Elementor.

This keeps your configuration list clean.

---

## ❓ FAQ

**Does it slow down my site?**  
No — processing runs on WordPress shutdown hook and uses optimized DB queries.

**Does it modify Elementor forms?**  
No — it only reads Elementor submission logs.

**Does it store user information?**  
No — data is only read and immediately sent to Slack.

---

## 💬 Support

If you need help or wish to report an issue, please open an issue on GitHub.