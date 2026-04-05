# SchemaPilot - WordPress JSON-LD Schema Manager

SchemaPilot is a lightweight WordPress plugin that lets you add custom structured data (JSON-LD) to any published page for better SEO and rich result eligibility.

---

## Features

* Add custom JSON-LD schema to any selected WordPress page
* Choose output location: head or footer
* Validates JSON before saving
* Clean admin UI with list, add, edit, and delete actions
* Lightweight and fast with no frontend assets

---

## Installation

1. Download or clone this repository:

   ```bash
   git clone https://github.com/zubairblti/SchemaPilot.git
   ```

2. Upload the plugin folder to:

   ```
   /wp-content/plugins/
   ```

3. Go to WordPress Admin → Plugins

4. Activate **SchemaPilot**

---

## Usage

* Open SchemaPilot -> Schema List
* Click Add New Schema
* Select any published page
* Paste your JSON-LD and choose head or footer
* Save and visit the selected page to verify output

---

## Supported Schema Types

SchemaPilot supports any valid JSON-LD schema. Examples include:

* WebSite
* Organization
* LocalBusiness
* Product
* Article
* FAQ
* BreadcrumbList

---

## Development Structure

```
SchemaPilot/
|-- assets/
|   |-- css/
|-- includes/
|   |-- class-schemapilot-admin.php
|   |-- class-schemapilot-schema-manager.php
|-- schemapilot.php
|-- index.php
```

---

## License

This project is licensed under the MIT License.

---

## 👨‍💻 Author

Developed by **Zubair Blti**

GitHub: https://github.com/zubairblti

---

## ⭐ Support

If you like this plugin, please ⭐ star the repository and share it!

---