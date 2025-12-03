# CSS Migration Guide

## Overview
The system has been updated to use the new SIA Generic Design System. The new CSS structure is now in place and ready to use.

## New CSS Structure

### Files Created:
1. **`css/global.css`** - Global variables, typography, and base styles
2. **`css/components.css`** - Main component imports file
3. **`css/components/`** - Component-specific CSS files:
   - `button.css`
   - `modal.css`
   - `table.css`
   - `textfield.css`
   - `select.css`
   - `pagination.css`
   - `tab.css`

### JavaScript Components:
- **`js/components/modal.js`** - Modal functionality
- **`js/components/textfield.js`** - Password visibility toggle
- **`js/components/pagination.js`** - Pagination controls
- **`js/components/tab.js`** - Tab navigation

### Assets:
- **`icons.svg`** - SVG icon sprite file

## Component Usage

### Buttons
```html
<!-- Primary Button -->
<button class="component__button --primary">
    <span>Button Text</span>
</button>

<!-- Secondary Button -->
<button class="component__button --secondary">
    <span>Button Text</span>
</button>

<!-- Critical Button -->
<button class="component__button --critical">
    <span>Button Text</span>
</button>
```

### Text Fields
```html
<!-- With Icon -->
<div class="component__textfield">
    <svg>
        <use href="../icons.svg#search"></use>
    </svg>
    <input type="text" placeholder="Search..." />
</div>

<!-- Without Icon -->
<div class="component__textfield --no-icon">
    <input type="text" placeholder="Email" />
</div>

<!-- Password Field -->
<div class="component__textfield">
    <svg>
        <use href="../icons.svg#placeholder"></use>
    </svg>
    <input id="password" type="password" placeholder="Password" />
    <svg class="visibility-toggle">
        <use href="../icons.svg#placeholder"></use>
    </svg>
</div>
```

### Tables
```html
<div class="component__table">
    <table>
        <thead>
            <tr>
                <th>Header 1</th>
                <th>Header 2</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Data 1</td>
                <td>Data 2</td>
            </tr>
        </tbody>
    </table>
</div>
```

### Modals
```html
<div id="modal" class="component__modal">
    <section class="component__modal-content">
        <header>
            <div class="modal-content__icon">
                <svg id="modalIcon">
                    <use href="../icons.svg#placeholder"></use>
                </svg>
            </div>
            <h1 id="modalHeading" class="modal-content__heading">Modal Title</h1>
        </header>
        <p id="modalDescription">Modal description text</p>
        <footer>
            <button id="modalCancelButton" class="component__button --critical">
                <span id="modalCancelText">Cancel</span>
            </button>
            <button id="modalConfirmButton" class="component__button --primary">
                <span id="modalConfirmText">Confirm</span>
            </button>
        </footer>
    </section>
</div>
```

**JavaScript Usage:**
```javascript
// Include jQuery and modal.js
showModal({
    type: 'success', // or 'warning', 'error'
    heading: 'Success!',
    description: 'Operation completed successfully',
    confirmText: 'OK',
    onConfirm: () => {
        // Your callback
    }
});
```

### Select/Dropdown
```html
<!-- With Icon -->
<div class="component__select">
    <svg>
        <use href="../icons.svg#placeholder"></use>
    </svg>
    <select name="option">
        <option value="1">Option 1</option>
    </select>
</div>

<!-- Without Icon -->
<div class="component__select --no-icon">
    <select name="option">
        <option value="1">Option 1</option>
    </select>
</div>
```

### Pagination
```html
<div class="component__pagination">
    <section class="component__pagination-button"></section>
    <section class="component__pagination-page">
        <span>Showing page</span>
        <div class="component__select --no-icon">
            <select name="page"></select>
        </div>
        <span>of Y</span>
    </section>
</div>
```

### Tabs
```html
<div id="componentTab" class="component__tab">
    <nav>
        <span class="component__tab-navigation --active" data-content="content1">Tab 1</span>
        <span class="component__tab-navigation" data-content="content2">Tab 2</span>
    </nav>
    <section>
        <div id="content1" class="component__tab-content --active">
            <section>Tab 1 Content</section>
        </div>
        <div id="content2" class="component__tab-content">
            <section>Tab 2 Content</section>
        </div>
    </section>
</div>
```

## Migration Steps

1. **Update HTML files** to use new component classes where applicable
2. **Replace Font Awesome icons** with SVG icons from `icons.svg` where using new components
3. **Update button classes** from `btn-primary`, `btn-secondary` to `component__button --primary`, `component__button --secondary`
4. **Update input fields** to use `component__textfield` wrapper
5. **Update tables** to use `component__table` wrapper
6. **Include component JavaScript** files where needed:
   - `js/components/modal.js` for modals
   - `js/components/textfield.js` for password fields
   - `js/components/pagination.js` for pagination
   - `js/components/tab.js` for tabs

## Notes

- The new design system uses CSS custom properties (variables) defined in `global.css`
- All component classes follow the pattern: `component__[component-name]`
- Modifiers use the `--[modifier-name]` pattern (e.g., `--primary`, `--no-icon`)
- Backend functionality remains unchanged - only CSS classes need updating
- Tailwind utilities are still available for layout and spacing if needed

## Color Variables

The new design system uses these CSS variables:
- `--color-primary` - Primary blue color
- `--color-background` - Background color
- `--color-surface` - Surface/card background
- `--color-text` - Main text color
- `--color-text-muted` - Muted text color
- `--color-success`, `--color-warning`, `--color-error` - Status colors
- `--color-border` - Border color

See `global.css` for the complete list of available variables.
