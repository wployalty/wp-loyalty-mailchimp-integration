# Plugin Implementation Plan: React-Based Admin UI

This document outlines the architecture and step-by-step instructions for creating a new WordPress plugin that mirrors the structure of `wll-loyalty-launcher`, featuring a React-powered admin interface embedded within a standard PHP plugin structure.

## 1. Architecture Overview

- **PHP Side**: Follows an MVC pattern (`App/`) to handle routing, menu creation, and data API.
- **React Side**: Lives in a dedicated source folder (`admin-ui/`), uses Webpack to bundle code, and outputs to `assets/admin/js/`.
- **Integration**: The PHP side enqueues the `bundle.js` and provides a root `<div>` for React to mount.

## 2. Folder Structure

Create the following file tree for your new plugin (e.g., `my-new-plugin`):

```text
my-new-plugin/
├── App/                 <-- MVC Backend
│   ├── Controller/
│   │   ├── Admin/       
│   │   └── Common.php   (Passes state to React)
│   ├── Router.php       (AJAX Endpoints)
│   ├── Setup.php        (Hooks)
│   └── View/
│       └── Admin/
│           └── main.php (HTML Root)
├── assets/              <-- Build Output
│   └── admin/
│       └── js/          (Target for Webpack)
├── admin-ui/            <-- React Source (Start Here)
│   ├── src/
│   │   ├── components/  (Navbar, TitleActionContainer)
│   │   ├── pages/       (Your New Tabs)
│   │   ├── App.js
│   │   └── index.js
│   ├── package.json
│   ├── webpack.config.js
│   ├── tailwind.config.js
│   └── postcss.config.js
└── my-new-plugin.php    (Main Entry File)
```

## 3. Backend Setup (PHP)

### `App/Controller/Common.php`
This is the bridge. It MUST pass the `back_to_apps_url` for the backend UI to work correctly.

```php
public static function getLocalData() {
    // ... security and nonce checks ...
    $data = [
        'plugin_name' => 'My New Plugin',
        'version'     => '1.0.0',
        'common'      => [
            'back_to_apps_url' => admin_url('admin.php?page=wployalty'), // Important for UI consistency
        ],
        // ...
    ];
    wp_send_json_success($data);
}
```

### `App/View/Admin/main.php`
The simplicity of the React mount point:
```php
<div id="wll-admin-root">
    <!-- Loaders can go here -->
</div>
```

## 4. Frontend Setup (React)

### Dependencies
Inside `admin-ui/`, run:
```bash
npm init -y
npm install react react-dom react-router-dom axios
npm install --save-dev webpack webpack-cli babel-loader @babel/core @babel/preset-env @babel/preset-react css-loader style-loader postcss-loader tailwindcss postcss autoprefixer
```

### `admin-ui/webpack.config.js`
Ensure the output points to the parent assets folder.

```javascript
const path = require('path');
module.exports = {
  entry: './src/index.js',
  output: {
    path: path.resolve(__dirname, '../assets/admin/js'), 
    filename: 'app.bundle.js',
  },
  module: {
    rules: [
      { test: /\.(js|jsx)$/, exclude: /node_modules/, use: 'babel-loader' },
      { test: /\.css$/i, use: ['style-loader', 'css-loader', 'postcss-loader'] },
    ],
  },
};
```

### `admin-ui/tailwind.config.js`
Configure the content path correctly:
```javascript
module.exports = {
  content: ["./src/**/*.{js,jsx}"], 
  // Copy theme/colors from original plugin to match style
}
```

## 5. UI Implementation (The Shell)

To maintain the exact look and feel:

1.  **Reuse `TitleActionContainer.js`**: Copy this component from the original plugin. It serves as the standard header with "Back", "Reset", and "Save" buttons.
2.  **Define New Tabs**:
    - Modify `src/components/navbar/Navbar.js` to list your new tabs (e.g., "General", "Email").
    - Modify `src/components/routes/RouterContainer.js` to map routes to your new components.

### Example Page Component
```javascript
import React from 'react';
import TitleActionContainer from "../components/Common/TitleActionContainer";

const MyNewPage = () => {
    return (
        <div className="flex flex-col gap-5">
            <TitleActionContainer 
                title="General Settings" 
                saveAction={() => console.log('saving...')} 
            />
            <div className="bg-white p-6 rounded shadow">
                <h1>My Custom Content</h1>
            </div>
        </div>
    );
};
export default MyNewPage;
```

## 6. Development Workflow

1.  Open terminal in `admin-ui/`.
2.  Run `npm run start` (maps to `webpack --watch`).
3.  Edit React files -> Webpack rebuilds to `assets/`.
4.  Refresh WordPress Admin to see changes.
