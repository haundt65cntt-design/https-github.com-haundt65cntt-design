# 🤝 Contributing to Chat System

Thank you for considering contributing to the **Chat System**! Your help is essential for making this project even better. 💖

## 🧾 Table of Contents

- [How to Contribute](#-how-to-contribute)
- [Contribution Guidelines](#-contribution-guidelines)
- [Pull Request Process](#-pull-request-process)
- [Reporting Issues](#-reporting-issues)
- [License](#-license)
- [Copyright Notice](#-copyright-notice)

---

## 🛠️ How to Contribute

1. **Fork the repository** 📚
2. **Clone your fork**
   ```bash
   git clone https://github.com/NakiriFubuki/chatting-system.git
   ```
3. **Navigate into the project directory**
   ```bash
   cd chatting-system
   ```
4. **Create a new branch** 🌿
   ```bash
   git checkout -b feature/your-feature-name
   ```
5. **Set up the local environment**
   - Place the project under your web root (e.g. XAMPP `htdocs`)
   - Create a MySQL database named `chatting_system`
   - Import `database/schema.sql` (and run update scripts if needed)
   - Configure `config/database.php` with your DB credentials
   - Ensure `uploads/` directories are writable
6. **Make your changes** ✨
7. **Test thoroughly** (login, chat, friends, groups, forum, uploads)
8. **Commit your changes** 💾
   ```bash
   git commit -m "Add: A meaningful commit message"
   ```
9. **Push to your branch** 🚀
   ```bash
   git push origin feature/your-feature-name
   ```
10. **Create a Pull Request** 🛠️

---

## 📋 Contribution Guidelines

- Follow the existing PHP / HTML / CSS / JavaScript style and naming conventions.
- Prefer prepared statements for all SQL queries.
- Escape output to prevent XSS (`htmlspecialchars`).
- Keep commits focused and write clear commit messages.
- Add brief comments only where the logic is non-obvious.
- Do not commit secrets (`.env`, local DB passwords, private keys).

---

## ✅ Pull Request Process

- Branch from `main` (or the active development branch).
- Open your PR against `main`.
- Link related issues when applicable.
- Describe what changed and how to test it.
- Wait for review/approval before merging.

---

## 🐞 Reporting Issues

Found a bug or have a feature request? Open an [Issue](https://github.com/NakiriFubuki/chatting-system/issues) and include:

- Steps to reproduce
- Expected vs actual behavior
- Browser / PHP / MySQL versions when relevant
- Screenshots if the UI is involved

---

## ©️ Copyright Notice

Copyright © 2026 Eng Choon Hao. All Rights Reserved.

Unauthorized commercial use, redistribution, or resale of this project (including its UI) is prohibited unless you have explicit permission from the copyright holder.

---

## 📜 License

This project is licensed under the [MIT Non-Commercial License](LICENSE).

Happy Coding! 💻✨
