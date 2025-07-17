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
]

# MyST parser configuration
myst_enable_extensions = [
    "colon_fence",
    "deflist",
    "html_admonition",
    "substitution",
    "tasklist",
]

source_suffix = {
    '.rst': 'restructuredtext',
    '.md': 'markdown',
}

# The master document (entry point)
master_doc = 'index'

# Patterns to exclude when building docs
exclude_patterns = ['_build', 'Thumbs.db', '.DS_Store', '**/ipynb_checkpoints']

# HTML theme options
html_theme = 'sphinx_rtd_theme'
html_logo = 'images/logo.svg'
html_theme_options = {
    'logo_only': False,
    'display_version': True,
    'prev_next_buttons_location': 'bottom',
    'style_external_links': False,
    'vcs_pageview_mode': '',
    'style_nav_header_background': '#2980b9',
    'collapse_navigation': False,
    'sticky_navigation': True,
    'navigation_depth': 4,
    'includehidden': True,
    'titles_only': False
}