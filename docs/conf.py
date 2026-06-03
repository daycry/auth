# Configuration file for the Sphinx documentation builder.

project = 'Daycry Auth - Authentication Library for CodeIgniter 4'
copyright = '2025, Daycry'
author = 'daycry'
release = '1.0'

# Add extensions for MyST markdown support
extensions = [
    'myst_parser',
    'sphinx.ext.autodoc',
    'sphinx.ext.viewcode',
    'sphinx_copybutton',
]

# MyST parser configuration
myst_enable_extensions = [
    "colon_fence",
    "deflist",
    "html_admonition",
    "substitution",
    "tasklist",
    "linkify",
]

# Auto-generate header anchors (h1-h4) so in-page/cross-page links resolve.
myst_heading_anchors = 4

source_suffix = {
    '.rst': 'restructuredtext',
    '.md': 'markdown',
}

# The master document (entry point)
master_doc = 'index'

# Patterns to exclude when building docs.
# - README.md is the GitHub-facing folder index (duplicates index.md).
# - superpowers/ holds internal design specs & implementation plans, not part
#   of the published reference site.
exclude_patterns = [
    '_build',
    'Thumbs.db',
    '.DS_Store',
    '**/ipynb_checkpoints',
    'README.md',
    'superpowers/**',
]

# --------------------------------------------------------------------------
# Syntax highlighting (Pygments)
# --------------------------------------------------------------------------
# Most code fences are tagged (php, bash, json, html, http, javascript); the
# untagged blocks (diagrams, trees, console output) are tagged `text`. We set a
# vibrant light style and a separate dark style (used by Furo's dark mode).
highlight_language = 'text'
pygments_style = 'friendly'
pygments_dark_style = 'monokai'

# --------------------------------------------------------------------------
# HTML output — Furo theme
# --------------------------------------------------------------------------
html_theme = 'furo'
html_title = 'Daycry Auth'
html_logo = 'images/logo.svg'
html_static_path = ['_static']
html_css_files = ['custom.css']

html_theme_options = {
    "sidebar_hide_name": False,
    "navigation_with_keys": True,
    # Brand colour (kept close to the previous Read the Docs blue) for light
    # and dark modes.
    "light_css_variables": {
        "color-brand-primary": "#1f6feb",
        "color-brand-content": "#1f6feb",
        "color-admonition-title-background--note": "rgba(31, 111, 235, 0.1)",
        "font-stack": "Inter, -apple-system, BlinkMacSystemFont, Segoe UI, Roboto, Helvetica, Arial, sans-serif",
        "font-stack--monospace": "JetBrains Mono, Fira Code, SFMono-Regular, Menlo, Consolas, monospace",
    },
    "dark_css_variables": {
        "color-brand-primary": "#58a6ff",
        "color-brand-content": "#58a6ff",
    },
    # "Edit on GitHub" links + repo button.
    "source_repository": "https://github.com/daycry/auth/",
    "source_branch": "development",
    "source_directory": "docs/",
    "footer_icons": [
        {
            "name": "GitHub",
            "url": "https://github.com/daycry/auth",
            "html": (
                '<svg stroke="currentColor" fill="currentColor" stroke-width="0" '
                'viewBox="0 0 16 16"><path fill-rule="evenodd" d="M8 0C3.58 0 0 3.58 0 '
                '8c0 3.54 2.29 6.53 5.47 7.59.4.07.55-.17.55-.38 '
                '0-.19-.01-.82-.01-1.49-2.01.37-2.53-.49-2.69-.94-.09-.23-.48-.94-.82-1.13-.28-.15-.68-.52-.01-.53.63-.01 '
                '1.08.58 1.23.82.72 1.21 1.87.87 2.33.66.07-.52.28-.87.51-1.07-1.78-.2-3.64-.89-3.64-3.95 '
                '0-.87.31-1.59.82-2.15-.08-.2-.36-1.02.08-2.12 0 0 .67-.21 2.2.82.64-.18 1.32-.27 '
                '2-.27.68 0 1.36.09 2 .27 1.53-1.04 2.2-.82 2.2-.82.44 1.1.16 1.92.08 '
                '2.12.51.56.82 1.27.82 2.15 0 3.07-1.87 3.75-3.65 3.95.29.25.54.73.54 '
                '1.48 0 1.07-.01 1.93-.01 2.2 0 .21.15.46.55.38A8.013 8.013 0 0 0 16 8c0-4.42-3.58-8-8-8z"></path></svg>'
            ),
            "class": "",
        },
    ],
}
