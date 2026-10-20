# DoubleList Clone - PHP Classifieds Platform

A full-featured PHP-based classified ads website similar to DoubleList, built with pure PHP, MySQL, and modern CSS.

## Features

- ✅ User registration and authentication
- ✅ Create, edit, and delete listings
- ✅ Category-based browsing
- ✅ Search and filter functionality
- ✅ Location-based filtering
- ✅ Responsive design
- ✅ View counter for listings
- ✅ Auto-expiring listings (30 days)
- ✅ User dashboard
- ✅ Message system (basic structure)
- ✅ Report system (basic structure)

## Requirements

- PHP 7.4 or higher
- MySQL 5.7 or higher
- Apache/Nginx web server
- PDO PHP Extension

## Installation

1. **Clone or download this repository**

2. **Create a MySQL database**
   ```bash
   mysql -u root -p
   ```
   Then run the SQL commands from `database/schema.sql`

3. **Configure database connection**
   Edit `config/database.php` and update with your database credentials:
   ```php
   private $host = 'localhost';
   private $db_name = 'doublelist_clone';
   private $username = 'your_username';
   private $password = 'your_password';
   ```

4. **Set up your web server**
   Point your document root to the project directory

5. **Access the application**
   Navigate to `http://localhost/` in your browser

## Project Structure

```
doublelist-clone/
├── config/
│   └── database.php          # Database configuration
├── classes/
│   ├── User.php              # User authentication & management
│   ├── Listing.php           # Listing CRUD operations
│   └── Category.php          # Category management
├── views/
│   ├── header.php            # Header template
│   └── footer.php            # Footer template
├── assets/
│   └── css/
│       └── style.css         # Main stylesheet
├── database/
│   └── schema.sql            # Database schema
├── index.php                 # Homepage
├── login.php                 # Login page
├── register.php              # Registration page
├── create-listing.php        # Create new listing
├── listing.php               # View single listing
├── logout.php                # Logout handler
└── README.md                 # This file
```

## Security Considerations

⚠️ **Important**: This is a basic implementation. For production use, consider:

1. **Add CSRF protection** to all forms
2. **Implement rate limiting** for registration and login
3. **Add email verification** for new accounts
4. **Use HTTPS** in production
5. **Sanitize all user inputs** more thoroughly
6. **Add image upload capability** with proper validation
7. **Implement proper session management**
8. **Add IP-based blocking** for spam prevention
9. **Use prepared statements** (already implemented)
10. **Add Content Security Policy headers**

## Additional Features to Implement

- [ ] Email verification system
- [ ] Password reset functionality
- [ ] Private messaging system (fully functional)
- [ ] Image uploads for listings
- [ ] Admin panel
- [ ] User profiles
- [ ] Favorite/bookmark listings
- [ ] Email notifications
- [ ] Advanced search filters
- [✅] Location-based search with maps
- [ ] Mobile app API

## Usage

### Creating an Account
1. Click "Sign Up" in the navigation
2. Fill in your details
3. Login with your credentials

### Posting a Listing
1. Login to your account
2. Click "+ Post Ad"
3. Fill in the listing details
4. Submit

### Browsing Listings
1. Visit the homepage
2. Filter by category or location
3. Use the search bar for specific keywords
4. Click on any listing to view details

## License

This project is open-source and available for educational purposes.

## Disclaimer

This is a demonstration project. Always ensure compliance with local laws and regulations when running a classifieds website. Implement proper content moderation and age verification systems.

## Support

For issues or questions, please create an issue in the repository.
```
