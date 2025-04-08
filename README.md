# One Patch Security

**One Patch Security** is a WordPress plugin designed to enhance the security of your WordPress site by patching some of the application-layer vulnerabilities that come out-of-the-box with WordPress. 
Each feature can be toggled on or off via the settings page, allowing you to customize the plugin to your needs. If you're not sure what settings you need, we offer a free CLI testing tool, available on [github](https://github.com/mygithub]),  that will help you.

---

## Features

### Version 1.0.0
- **Remove WordPress Version Meta**: Hides the WordPress version from metadata to prevent information disclosure.
- **Prevent User Enumeration**:
    - Blocks user enumeration via the `author` query parameter.
    - Redirects author archive pages to the homepage.
- **Restrict REST API Access**:
    - Blocks non-logged-in users from accessing the REST API.
    - Disables specific REST endpoints (e.g., `/wp/v2/users`, `/wp/v2/plugins`).
- **Custom Login Error Message**: Replaces default login error messages with a generic message to prevent username enumeration.
- **Disable XML-RPC**:
    - Disables XML-RPC functionality to prevent remote access.
    - Removes the `pingback.ping` method from the XML-RPC API.
- **Force Secure Cookies**: Ensures cookies are only sent over HTTPS when the site is accessed via SSL.

### Roadmap for Version 2.0.0
- **Disallow File Editing in Dashboard**: Prevent theme and plugin file editing from the WordPress dashboard.
- **Disallow File Modifications**: Disable the ability to install or update plugins and themes via the dashboard.
- **Prevent PHP Execution in `/uploads`**: Block PHP file execution in the `/uploads` directory.
- **Disable RSS and Atom Feeds**: Optionally disable RSS and Atom feeds to reduce the attack surface.
- **Disable Application Passwords**: Optionally disable the use of application passwords for REST API authentication.
- **Various Housekeeping Updates**: To include breaking up th OnePatch class into multiple classes per option

## Installation

1. **Download the Plugin**:
    - Download the plugin ZIP file from the repository or WordPress plugin directory.

2. **Upload the Plugin**:
    - Go to **Plugins > Add New > Upload Plugin** in your WordPress admin dashboard.
    - Upload the ZIP file and click **Install Now**.

3. **Activate the Plugin**:
    - After installation, click **Activate Plugin**.

4. **Configure Settings**:
    - Go to **Settings > OnePatch Security** to enable or disable specific security features.

---

## Usage

Once activated, navigate to the Security page under Settings in the WordPress admin dashboard. Here you can enable whatever features your sites needs.
If you're not sure what settings you need, we offer a free CLI testing tool that will help you.
Available on [github](https://github.com/mygithub)


---

## Contributing

Contributions are welcome! If you'd like to contribute to the development of One Patch Security, please follow these steps:

1. Fork the repository.
2. Create a new branch for your feature or bug fix.
3. Submit a pull request with a detailed description of your changes.

---

## Changelog

### Version 1.0.0
- Initial release with core security features.

---

## License

This plugin is licensed under the **GNU General Public License v2 or later**. 

---

## Support

For support, feature requests, or bug reports, please open an issue on the [GitHub repository](https://github.com/your-repo/one-patch-security).

---

## About

OnePatch is developed and maintained by **[One Patch Security](https://onepatchsecurity.com)**, a company specializing in WordPress security. In addition to this plugin, One Patch Security offers **security audits** and **code review as a service** to help you secure your WordPress site. Visit [https://onepatchsecurity.com](https://onepatchsecurity.com) for more information.