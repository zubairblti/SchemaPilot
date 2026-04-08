# SchemaPilot - WordPress JSON-LD Schema Manager

SchemaPilot is a lightweight WordPress plugin that lets you add custom structured data (JSON-LD) to published Pages, Posts, or both for better SEO and rich result eligibility.

---

## Features

* Add custom JSON-LD schema to published Pages, Posts, or both
* Dashboard setting to choose:
  * Pages only
  * Posts only
  * Both Pages and Posts
* Add/Edit schema dropdown automatically follows the saved dashboard setting
* Page editor and Post editor meta box support for adding schema directly from the edit screen
* Editor meta box visibility is synced with the dashboard setting
* Choose output location: head or footer
* Validates JSON before saving
* Schema List includes search and Type filter for Pages and Posts
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

1. Open `SchemaPilot -> Dashboard`
2. Save your content setting:
   * Pages only
   * Posts only
   * Both Pages and Posts
3. Open `SchemaPilot -> Schema List`
4. Use search or the `Type` filter to find Page/Post schema entries
5. Click `Add New Schema`
6. Select published content based on your saved dashboard setting
7. Paste your JSON-LD and choose head or footer
8. Save and visit the selected Page or Post to verify output

---

## Dashboard Setting Sync

The dashboard content setting controls multiple parts of the plugin:

* If `Pages only` is selected:
  * Add/Edit schema dropdown shows only Pages
  * SchemaPilot meta box appears on Page edit screen
  * SchemaPilot meta box does not appear on Post edit screen

* If `Posts only` is selected:
  * Add/Edit schema dropdown shows only Posts
  * SchemaPilot meta box appears on Post edit screen
  * SchemaPilot meta box does not appear on Page edit screen

* If `Both Pages and Posts` is selected:
  * Add/Edit schema dropdown shows both Pages and Posts
  * SchemaPilot meta box appears on both Page and Post edit screens

This keeps the Dashboard, Schema List, Add/Edit form, and editor screens in sync.

---

## Editor Support

SchemaPilot supports direct schema management from:

* Page edit screen
* Post edit screen

You can add, update, or remove schema directly from the editor while using the same schema storage and output logic as the Schema List screen.

---

## Schema List Tools

On the `Schema List` page you can:

* Search schema entries
* Filter by Type:
  * All
  * Page
  * Post
* Bulk delete selected entries
* Edit or delete individual entries

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

```text
SchemaPilot/
|-- assets/
|   |-- css/
|   |-- js/
|-- includes/
|   |-- class-schemapilot-admin.php
|   |-- class-schemapilot-schema-manager.php
|-- schemapilot.php
|-- index.php
|-- README.md
```

---

## License

This project is licensed under the MIT License.

---

## Author

Developed by **Zubair Blti**

GitHub: https://github.com/zubairblti

---

## Support

If you like this plugin, please star the repository and share it.

---
