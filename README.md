# Khademni — Find Your Next Job in Tunisia

**Khademni** is Tunisia's premier recruitment platform connecting top talent with leading companies. Search thousands of jobs, build your profile, and land your dream career.

## 🚀 Features

- **Candidate Profiles**: Build a professional profile, upload your CV, and manage your applications.
- **Job Search**: Advanced search with filters for industry, location, and salary.
- **Secure Authentication**: Robust login and registration system using JWT (JSON Web Tokens).
- **Email Verification**: Automatic account verification to ensure a secure community.
- **Password Recovery**: Secure password reset flow for forgotten passwords.
- **Mobile Responsive**: Fully optimized for a seamless experience on all devices.

## 🛠️ Technology Stack

- **Frontend**: HTML5, CSS3 (Vanilla), JavaScript (ES6+).
- **Backend**: PHP 8.x.
- **Database**: MySQL.
- **Icons & Fonts**: FontAwesome 6, Google Fonts (Inter).

## 📂 Project Structure

- `index.html`: Main landing page.
- `login.html` / `register.html`: Authentication pages.
- `profile.html`: User dashboard.
- `api/`: Backend logic and API endpoints.
  - `Auth.php`: Authentication controller.
  - `Profile.php`: User profile management.
  - `Database.php`: Singleton database connection.
- `styles.css` / `auth.css`: Styling files.
- `script.js` / `auth.js`: Frontend logic.

## 🔧 Installation & Setup

1. **Clone the repository**:
   ```bash
   git clone https://github.com/ahmedmhirsi/khademni.git
   ```
2. **Setup Database**:
   - Import `database/schema.sql` into your MySQL server.
   ```sql
   CREATE DATABASE khademni;
   USE khademni;
   -- Run the schema.sql content here
   ```
3. **Configure API**:
   - Update `api/config.php` with your database credentials.
4. **Deploy**:
   - Serve the project using a local server like XAMPP, WAMP, or Apache.

---
&copy; 2025 Khademni. Made with ❤️ in Tunisia.
