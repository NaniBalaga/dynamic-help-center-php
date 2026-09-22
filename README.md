A fully custom, modern, and highly responsive Help Center platform developed exclusively for the **CONNECT SRMAP** student portal. 

Built from scratch using PHP, MySQL, and Vanilla JavaScript, this project features a stunning Light/Dark Glassmorphism UI, a custom Markdown parser, real-time global search, and a fully equipped Admin Editor for managing knowledge base articles.

## ✨ Key Features

### Frontend Experience
* **Glassmorphism UI:** A sleek, modern interface with a frosted-glass effect that seamlessly transitions between Light and Dark themes.
* **Dynamic Content Loading:** Articles load instantly via AJAX with a smooth UI skeleton loader, eliminating the need for page reloads.
* **Custom Markdown Parser:** Renders custom Markdown (`##`, `**`, `~~`, `>`, `\``, `---`) directly into beautifully styled HTML elements.
* **Real-Time Global Search:** A full-page search overlay that instantly filters through all articles, highlighting matching text using Regex.
* **Mobile-First Design:** A fully responsive layout with an off-canvas mobile sidebar and touch-friendly navigation.
* **Shareable Links:** Unique URL token routing allows users to easily copy and share direct links to specific articles.

### Admin Dashboard
* **Full CRUD Operations:** Effortlessly create, read, update, and delete support tabs (categories) and individual articles.
* **Live Markdown Preview:** A built-in dual-pane editor that allows admins to preview formatting in real-time before publishing.
* **Quick Format Toolbar:** One-click buttons to inject markdown syntax (Bold, Italic, Code, Links, etc.) into the editor.
* **Tab Management:** Group articles logically under customizable tabs with FontAwesome icons.

## 🛠️ Tech Stack
* **Backend:** PHP 8+, MySQL (via `mysqli`)
* **Frontend:** HTML5, Vanilla JavaScript, Custom CSS3
* **Icons & Typography:** FontAwesome 6, Google Fonts (Poppins)
* **Architecture:** Token-based routing and REST-like AJAX endpoints

## 📂 Project Structure
| File | Description |
|------|-------------|
| `help_center.php` | The main user-facing knowledge base. Handles the UI, AJAX requests, search logic, and frontend Markdown parsing. |
| `admin_help.php` | The secure backend dashboard where administrators can manage tabs, write articles, and preview content. |
| `handle_submissions.php` | The processing engine that handles all POST requests, CRUD logic, and database communications. |
| `../db_connect.php` | *(Required)* Your external database connection file. |
