# 📱 Mobile Responsiveness Guide — Umoja Sacco

Your application now supports **full mobile responsiveness** for phones, tablets, and desktops!

## ✨ What's New

### Mobile-First Architecture
- **Auto-responsive layout** that adapts to any screen size
- **Collapsible sidebar** on mobile (swipe or tap hamburger menu)
- **Touch-friendly UI** with 44px+ minimum tap targets
- **Responsive tables** that stack on mobile
- **Device detection** (phone, tablet, desktop)

### Files Added

```
public/assets/css/mobile-responsive.css       — Mobile CSS with media queries
public/assets/js/mobile-menu.js               — Mobile menu & device detection
api/v1/device-detection.php                   — Device detection API
MOBILE_RESPONSIVENESS.md                      — This guide
```

### Headers Updated
- `admin/layouts/header.php` — includes mobile-responsive.css
- `member/layouts/header.php` — includes mobile-responsive.css
- `admin/layouts/footer.php` — includes mobile-menu.js
- `member/layouts/footer.php` — includes mobile-menu.js

---

## 📐 Breakpoints

The app uses these responsive breakpoints:

| Breakpoint | Screen Width | Devices |
|-----------|--------------|---------|
| **mobile** | ≤ 640px | iPhones, small Android phones |
| **tablet** | 641-1024px | iPads, large Android tablets |
| **desktop** | ≥ 1025px | Desktops, laptops |

---

## 🚀 Features

### 1. **Auto-Responsive Navigation**
- On **mobile**: Sidebar hidden by default, hamburger menu toggle
- On **tablet**: Sidebar visible but narrower (220px)
- On **desktop**: Full sidebar (260px)

**Usage:**
```html
<!-- Hamburger button auto-created -->
<!-- Click to toggle sidebar on mobile -->
```

### 2. **Touch-Friendly Controls**
- All buttons: minimum 44px height × 44px width
- Forms & inputs: 44px height, 16px font size (prevents iOS zoom)
- Dropdown menus: scrollable on mobile
- Modals: fit within 85% of viewport

### 3. **Responsive Tables**
On mobile, tables stack vertically:
```
Desktop View:        Mobile View:
┌──────────────┐    ┌─────────────────┐
│ ID │ Name  │     │ ID: 123         │
├──────────────┤    │ Name: John      │
│123│ John   │     ├─────────────────┤
│456│ Jane   │     │ ID: 456         │
└──────────────┘    │ Name: Jane      │
                    └─────────────────┘
```

Add `data-label` to table cells:
```html
<table class="table">
  <thead>
    <tr><th>ID</th><th>Name</th></tr>
  </thead>
  <tbody>
    <tr>
      <td data-label="ID">123</td>
      <td data-label="Name">John</td>
    </tr>
  </tbody>
</table>
```

### 4. **Swipe Gestures**
- **Swipe right** from left edge → Opens sidebar (mobile)
- **Swipe left** → Closes sidebar
- **ESC key** → Closes sidebar (keyboard)

### 5. **Device Detection API**
Call from JavaScript:
```javascript
// Get device info
console.log(DeviceDetector.getDeviceType());  // 'phone', 'tablet', or 'desktop'
console.log(DeviceDetector.getBreakpoint());  // 'mobile', 'tablet', or 'desktop'
console.log(DeviceDetector.getViewportWidth());  // 375
```

---

## 🔌 Device Detection API

### Endpoint: `/api/v1/device-detection.php`

**Detect device & get pause state:**
```bash
curl "https://your-app/api/v1/device-detection.php?action=detect&vw=375&vh=667&touch=true"
```

**Response:**
```json
{
  "success": true,
  "action": "detect",
  "data": {
    "device": {
      "type": "phone",
      "breakpoint": "mobile",
      "userAgent": "Mozilla/5.0...",
      "viewport": { "width": 375, "height": 667 },
      "touchCapable": true,
      "orientation": "portrait"
    },
    "pauseState": {
      "isPaused": false,
      "pausedAt": null,
      "reason": null
    },
    "recommendation": {
      "shouldPause": true,
      "reason": "Mobile phone detected - reduced refresh frequency to save battery"
    }
  }
}
```

### API Actions

| Action | Method | Purpose |
|--------|--------|---------|
| `detect` | GET | Get device info & recommendations |
| `pause` | POST | Pause auto-refresh |
| `resume` | POST | Resume auto-refresh |
| `toggle` | POST | Toggle pause state |
| `getState` | GET | Get current pause state |

**Examples:**
```bash
# Pause auto-refresh
curl -X POST "https://your-app/api/v1/device-detection.php?action=pause&reason=Battery%20saver"

# Resume
curl -X POST "https://your-app/api/v1/device-detection.php?action=resume"

# Get current state
curl "https://your-app/api/v1/device-detection.php?action=getState"
```

---

## 🎨 Using Responsive CSS Classes

### Hide/Show by Device

```html
<!-- Hide on mobile, show on desktop -->
<div class="hide-mobile">Desktop-only content</div>

<!-- Hide on desktop, show on mobile -->
<div class="hide-desktop">Mobile-only content</div>
```

### Custom Media Queries

```css
/* Mobile phones (≤ 640px) */
@media (max-width: 640px) {
  .my-element { font-size: 14px; }
}

/* Tablets (641-1024px) */
@media (min-width: 641px) and (max-width: 1024px) {
  .my-element { font-size: 15px; }
}

/* Desktop (≥ 1025px) */
@media (min-width: 1025px) {
  .my-element { font-size: 16px; }
}

/* Touch devices */
@media (hover: none) and (pointer: coarse) {
  button { min-height: 48px; }
}
```

---

## 🧪 Testing on Real Devices

### Desktop
```bash
# Start your dev server
# Open in Chrome DevTools → Ctrl+Shift+M (Toggle Device Toolbar)
```

### iPhone Simulator
```bash
# macOS: Use Safari or Xcode Simulator
# Windows: Use Chrome DevTools device emulation
```

### Android Emulator
```bash
# Use Android Studio emulator or Chrome Android emulation
```

### Browser DevTools
1. Open Chrome DevTools (`F12`)
2. Click **Device Toolbar** icon (Ctrl+Shift+M)
3. Select device from dropdown
4. Test responsive layout

---

## 🔧 Customization

### Change Breakpoint Sizes

Edit `/public/assets/css/mobile-responsive.css`:
```css
/* Default: 640px for mobile */
@media (max-width: 640px) { ... }

/* Change to 768px for mobile */
@media (max-width: 768px) { ... }
```

### Adjust Sidebar Width on Tablet

Edit `layout.css`:
```css
/* Default tablet sidebar */
.admin-sidebar { width: 220px; }

/* Change to 180px */
.admin-sidebar { width: 180px; }
```

### Change Touch Target Size

Edit `mobile-responsive.css`:
```css
/* Default: 44px minimum */
.btn { min-height: 44px; min-width: 44px; }

/* Increase to 48px for more touch-friendly */
.btn { min-height: 48px; min-width: 48px; }
```

---

## 🐛 Debugging

### Check Device Info in Console

```javascript
// In browser console
DeviceDetector.log();  // Shows device info
```

Output:
```
📱 Device Detector Info
Device Type: phone
Breakpoint: mobile
Viewport: 375x667
Orientation: Portrait
User Agent: Mozilla/5.0...
```

### Manually Test Pause/Resume

```javascript
// Open console, test pause state API
fetch('/api/v1/device-detection.php?action=pause&reason=test')
  .then(r => r.json())
  .then(d => console.log(d.data));
```

---

## 📋 Mobile Checklist

- ✅ Sidebar collapses on mobile
- ✅ All buttons ≥ 44px height/width
- ✅ Form inputs ≥ 44px height, 16px font
- ✅ Tables stack on mobile
- ✅ Swipe gestures work
- ✅ Touch-friendly dropdowns
- ✅ Modal fits 85% viewport
- ✅ Safe area for notched phones
- ✅ Landscape orientation handled
- ✅ Device detection API responds

---

## 🚀 Best Practices

### Do's
- ✅ Test on real devices (not just DevTools)
- ✅ Use 44px minimum for touch targets
- ✅ Provide feedback for all interactions
- ✅ Use semantic HTML
- ✅ Support landscape and portrait
- ✅ Respect safe area (notches, home bar)

### Don'ts
- ❌ Use `font-size: 16px` on inputs (prevents iOS zoom)
- ❌ Create horizontal scroll on mobile
- ❌ Hide navigation on smaller screens (make it collapsible)
- ❌ Use small hover states as only interaction method
- ❌ Forget to test with slow network (use Chrome throttling)

---

## 📱 Screen Sizes

### Common Mobile Sizes
| Device | Width | Height | Ratio |
|--------|-------|--------|-------|
| iPhone 6/7/8 | 375px | 667px | 9:16 |
| iPhone 12/13 | 390px | 844px | 9:19.5 |
| iPhone 14 Pro | 393px | 852px | 9:19.5 |
| Galaxy S10 | 360px | 800px | 9:20 |
| Galaxy S21 | 360px | 800px | 9:20 |
| iPad (6th gen) | 768px | 1024px | 3:4 |
| iPad Pro 11" | 834px | 1194px | 1.43:1 |

### Safe Area (Notches)
```css
/* iPhone notch support */
@supports (padding: max(0px)) {
  .topbar, .main-content {
    padding-left: max(1rem, env(safe-area-inset-left));
    padding-right: max(1rem, env(safe-area-inset-right));
  }
}
```

---

## 🔗 Resources

- [MDN Web Docs - Responsive Design](https://developer.mozilla.org/en-US/docs/Learn/CSS/CSS_layout/Responsive_Design)
- [Apple Human Interface Guidelines](https://developer.apple.com/design/human-interface-guidelines)
- [Material Design - Mobile Patterns](https://material.io/design/platform-guidance/android-bars.html)
- [Bootstrap 5 Responsive Design](https://getbootstrap.com/docs/5.3/getting-started/introduction/#responsive-meta-tag)

---

## 📞 Support

For issues with mobile responsiveness:

1. **Check DevTools** — Use Chrome Device Toolbar
2. **Inspect Element** — Find CSS conflicts
3. **Test on Real Device** — Emulators aren't always accurate
4. **Check Breakpoint** — Verify correct media query triggers
5. **Review Logs** — Check `MobileDebug.log()` in console

---

**Happy mobile-first development!** 📱✨
