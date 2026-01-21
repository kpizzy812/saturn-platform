# Saturn UI Premium Redesign Plan

## Research Summary

Based on [modern UI/UX trends for 2025](https://medium.com/@frameboxx81/dark-mode-and-glass-morphism-the-hottest-ui-trends-in-2025-864211446b54) and [SaaS dashboard best practices](https://uitop.design/blog/design/top-dashboard-design-trends/):

### Key Design Principles
1. **Dark Mode + Glassmorphism** - Frosted glass effects on dark backgrounds
2. **Micro-interactions** - Subtle animations that respond to user actions
3. **Depth & Layering** - Multiple layers with shadows for hierarchy
4. **Restraint** - Limited color palette, strategic accents only
5. **Accessibility** - WCAG 2.2 compliance, proper contrast ratios

---

## Saturn Branding

### Logo Concept
Saturn - планета с кольцами. Логотип должен быть:
- **Минималистичный** - простая форма планеты с кольцом
- **Узнаваемый** - уникальный силуэт
- **Масштабируемый** - работает от 16px до любого размера

```
Варианты логотипа:

1. Абстрактный Сатурн (рекомендуется):
   ○═══○  - Кольцо проходит через планету

2. Геометрический:
   ◐━━━  - Полукруг с линией-кольцом

3. Буква S как планета:
   Стилизованная S с орбитальным кольцом
```

### Logo SVG Design
```svg
<!-- Saturn Logo - Minimalist -->
<svg viewBox="0 0 32 32" fill="none">
  <!-- Planet -->
  <circle cx="16" cy="16" r="8" fill="currentColor"/>
  <!-- Ring -->
  <ellipse cx="16" cy="16" rx="14" ry="4"
           stroke="currentColor" stroke-width="2"
           fill="none" opacity="0.6"/>
  <!-- Highlight -->
  <circle cx="13" cy="13" r="2" fill="white" opacity="0.3"/>
</svg>
```

### Brand Name Styling
- **Font**: Inter или SF Pro Display (современный, читаемый)
- **Weight**: 600-700 (semi-bold to bold)
- **Color**: Градиент от primary (#6366f1) к фиолетовому (#8b5cf6)
- **Effect**: Subtle glow на hover
- **Spacing**: Letter-spacing: -0.02em (для премиум вида)

```css
.saturn-brand {
  font-weight: 700;
  font-size: 1.25rem;
  letter-spacing: -0.02em;
  background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  text-shadow: 0 0 30px rgba(99, 102, 241, 0.3);
}
```

---

## Phase 1: Design Tokens & Foundation

### 1.1 Color Palette
```css
/* Background Layers - от тёмного к светлому */
--bg-base: #08080d;        /* Самый глубокий фон */
--bg-elevated-1: #0f0f18;  /* Карточки, панели */
--bg-elevated-2: #161621;  /* Модалки, dropdown */
--bg-elevated-3: #1c1c2a;  /* Hover состояния */

/* Glassmorphism - эффект матового стекла */
--glass-bg: rgba(255, 255, 255, 0.03);
--glass-border: rgba(255, 255, 255, 0.06);
--glass-blur: 12px;

/* Accent Colors - акцентные цвета */
--primary: #6366f1;        /* Indigo - основные действия */
--primary-glow: rgba(99, 102, 241, 0.15);
--success: #10b981;        /* Зелёный - успех */
--warning: #f59e0b;        /* Янтарный - предупреждение */
--danger: #ef4444;         /* Красный - ошибки */

/* Text Hierarchy - иерархия текста */
--text-primary: #f8fafc;   /* Заголовки, важное */
--text-secondary: #94a3b8; /* Основной текст */
--text-muted: #64748b;     /* Подписи, подсказки */
--text-subtle: #475569;    /* Неактивный, placeholder */
```

### 1.2 Shadow System
```css
/* Layered shadows - многослойные тени для глубины */
--shadow-xs: 0 1px 2px rgba(0, 0, 0, 0.3);
--shadow-sm: 0 2px 4px rgba(0, 0, 0, 0.3), 0 1px 2px rgba(0, 0, 0, 0.4);
--shadow-md: 0 4px 8px rgba(0, 0, 0, 0.3), 0 2px 4px rgba(0, 0, 0, 0.4);
--shadow-lg: 0 8px 16px rgba(0, 0, 0, 0.3), 0 4px 8px rgba(0, 0, 0, 0.4);
--shadow-xl: 0 16px 32px rgba(0, 0, 0, 0.4), 0 8px 16px rgba(0, 0, 0, 0.3);

/* Glow effects - свечение для активных элементов */
--glow-primary: 0 0 20px rgba(99, 102, 241, 0.3);
--glow-success: 0 0 20px rgba(16, 185, 129, 0.3);
--glow-warning: 0 0 20px rgba(245, 158, 11, 0.3);
--glow-danger: 0 0 20px rgba(239, 68, 68, 0.3);
```

### 1.3 Border Radius
```css
--radius-sm: 6px;    /* Мелкие элементы */
--radius-md: 10px;   /* Карточки, кнопки */
--radius-lg: 14px;   /* Модалки, панели */
--radius-xl: 20px;   /* Большие контейнеры */
```

---

## Phase 2: Status Animations

### 2.1 Service Status Indicators
```css
/* Running/Online - пульсирующий зелёный */
@keyframes statusOnline {
  0%, 100% {
    box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.4);
    opacity: 1;
  }
  50% {
    box-shadow: 0 0 0 6px rgba(16, 185, 129, 0);
    opacity: 0.8;
  }
}
.status-online {
  background: #10b981;
  animation: statusOnline 2s ease-in-out infinite;
}

/* Deploying/Building - вращающийся индикатор */
@keyframes statusDeploying {
  0% { transform: rotate(0deg); }
  100% { transform: rotate(360deg); }
}
.status-deploying {
  background: conic-gradient(#f59e0b 0%, transparent 60%);
  animation: statusDeploying 1s linear infinite;
}

/* Stopped - статичный серый */
.status-stopped {
  background: #64748b;
  opacity: 0.6;
}

/* Error/Failed - мигающий красный */
@keyframes statusError {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.4; }
}
.status-error {
  background: #ef4444;
  animation: statusError 1s ease-in-out infinite;
}

/* Initializing - плавное появление */
@keyframes statusInit {
  0%, 100% { opacity: 0.3; }
  50% { opacity: 1; }
}
.status-initializing {
  background: #3b82f6;
  animation: statusInit 1.5s ease-in-out infinite;
}
```

### 2.2 Deployment Card Animations
```css
/* Active deployment - светящаяся рамка */
.deployment-active {
  border-color: #10b981;
  box-shadow: 0 0 0 1px rgba(16, 185, 129, 0.2),
              0 0 20px rgba(16, 185, 129, 0.1);
}

/* Building deployment - анимированная рамка */
@keyframes buildingBorder {
  0% { border-color: rgba(245, 158, 11, 0.3); }
  50% { border-color: rgba(245, 158, 11, 0.8); }
  100% { border-color: rgba(245, 158, 11, 0.3); }
}
.deployment-building {
  animation: buildingBorder 2s ease-in-out infinite;
}
```

### 2.3 Progress Indicators
```css
/* Build progress bar */
@keyframes progressShimmer {
  0% { background-position: -200% 0; }
  100% { background-position: 200% 0; }
}
.progress-bar {
  background: linear-gradient(
    90deg,
    rgba(99, 102, 241, 0.3) 0%,
    rgba(99, 102, 241, 0.8) 50%,
    rgba(99, 102, 241, 0.3) 100%
  );
  background-size: 200% 100%;
  animation: progressShimmer 1.5s ease-in-out infinite;
}
```

---

## Phase 3: Component Upgrades

### 3.1 Buttons
- **Default**: Градиентный фон, свечение при hover
- **Ghost**: Прозрачный, рамка появляется при hover
- **Secondary**: Glassmorphism фон
- **Animations**:
  - Scale при клике: `transform: scale(0.98)`
  - Плавные переходы: 200ms
  - Glow эффект при focus

### 3.2 Cards
- **Background**: Glassmorphism с backdrop-blur
- **Border**: 1px rgba(255,255,255,0.06)
- **Hover**: Подъём + усиление тени + свечение рамки
- **Active state**: Светящееся кольцо

### 3.3 Inputs
- **Background**: Темнее карточки (--bg-base)
- **Focus**: Светящаяся рамка primary цвета
- **Transition**: Плавное изменение цвета рамки

### 3.4 Dropdowns
- **Background**: Glassmorphism с сильным blur
- **Animation**: Scale + fade от точки триггера
- **Items**: Мягкая подсветка при hover

### 3.5 Badges/Tags
- **Success**: Зелёный фон со свечением
- **Warning**: Янтарный с пульсацией
- **Error**: Красный с привлечением внимания
- **Info**: Синий, нейтральный

---

## Phase 4: Essential Animations

### 4.1 Keyframes
```css
/* Появление элемента */
@keyframes fadeIn {
  from { opacity: 0; transform: translateY(8px); }
  to { opacity: 1; transform: translateY(0); }
}

/* Пульсация для live индикаторов */
@keyframes pulse {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.5; }
}

/* Shimmer для загрузки */
@keyframes shimmer {
  0% { background-position: -200% 0; }
  100% { background-position: 200% 0; }
}

/* Bounce для кнопок */
@keyframes scaleBounce {
  0% { transform: scale(1); }
  50% { transform: scale(0.95); }
  100% { transform: scale(1); }
}

/* Slide in для панелей */
@keyframes slideInRight {
  from { transform: translateX(100%); opacity: 0; }
  to { transform: translateX(0); opacity: 1; }
}

/* Появление модалки */
@keyframes modalIn {
  from { opacity: 0; transform: scale(0.95); }
  to { opacity: 1; transform: scale(1); }
}
```

### 4.2 Hover Effects
- **Cards**: Подъём + тень + свечение рамки
- **Buttons**: Смена фона + scale
- **Links**: Подчёркивание слева направо
- **Icons**: Поворот или scale

### 4.3 Transition Timing
- Цвета: 200ms ease-out
- Transform: 150ms ease-out
- Тени: 200ms ease-out

---

## Full Page Audit

### Authentication (10 pages)
| Page | File | Status |
|------|------|--------|
| Login | `Auth/Login.tsx` | 🔄 Needs update |
| Register | `Auth/Register.tsx` | 🔄 Needs update |
| Forgot Password | `Auth/ForgotPassword.tsx` | 🔄 Needs update |
| Reset Password | `Auth/ResetPassword.tsx` | 🔄 Needs update |
| Verify Email | `Auth/VerifyEmail.tsx` | 🔄 Needs update |
| Accept Invite | `Auth/AcceptInvite.tsx` | 🔄 Needs update |
| Onboarding | `Auth/Onboarding/Index.tsx` | 🔄 Needs update |
| 2FA Setup | `Auth/TwoFactor/Setup.tsx` | 🔄 Needs update |
| 2FA Verify | `Auth/TwoFactor/Verify.tsx` | 🔄 Needs update |
| OAuth Connect | `Auth/OAuth/Connect.tsx` | 🔄 Needs update |

### Dashboard & Projects (6 pages)
| Page | File | Status |
|------|------|--------|
| Dashboard | `Dashboard.tsx` | ✅ Good base |
| Projects List | `Projects/Index.tsx` | 🔄 Needs update |
| Project Canvas | `Projects/Show.tsx` | ✅ Updated |
| Create Project | `Projects/Create.tsx` | 🔄 Needs update |
| Environments | `Projects/Environments.tsx` | 🔄 Needs update |
| Project Variables | `Projects/Variables.tsx` | 🔄 Needs update |
| Local Setup | `Projects/LocalSetup.tsx` | 🔄 Needs update |

### Services (12 pages)
| Page | File | Status |
|------|------|--------|
| Service Detail | `Services/Show.tsx` | ✅ Good base |
| Deployments | `Services/Deployments.tsx` | 🔄 Needs update |
| Build Logs | `Services/BuildLogs.tsx` | 🔄 Needs update |
| Runtime Logs | `Services/Logs.tsx` | 🔄 Needs update |
| Metrics | `Services/Metrics.tsx` | 🔄 Needs update |
| Variables | `Services/Variables.tsx` | 🔄 Needs update |
| Settings | `Services/Settings.tsx` | 🔄 Needs update |
| Domains | `Services/Domains.tsx` | 🔄 Needs update |
| Health Checks | `Services/HealthChecks.tsx` | 🔄 Needs update |
| Webhooks | `Services/Webhooks.tsx` | 🔄 Needs update |
| Networking | `Services/Networking.tsx` | 🔄 Needs update |
| Scaling | `Services/Scaling.tsx` | 🔄 Needs update |
| Rollbacks | `Services/Rollbacks.tsx` | 🔄 Needs update |

### Databases (13 pages)
| Page | File | Status |
|------|------|--------|
| Databases List | `Databases/Index.tsx` | 🔄 Needs update |
| Database Detail | `Databases/Show.tsx` | 🔄 Needs update |
| Create Database | `Databases/Create.tsx` | 🔄 Needs update |
| Overview | `Databases/Overview.tsx` | 🔄 Needs update |
| Backups | `Databases/Backups.tsx` | 🔄 Needs update |
| Logs | `Databases/Logs.tsx` | 🔄 Needs update |
| Tables | `Databases/Tables.tsx` | 🔄 Needs update |
| Query Editor | `Databases/Query.tsx` | 🔄 Needs update |
| Users | `Databases/Users.tsx` | 🔄 Needs update |
| Extensions | `Databases/Extensions.tsx` | 🔄 Needs update |
| Import | `Databases/Import.tsx` | 🔄 Needs update |
| Connections | `Databases/Connections.tsx` | 🔄 Needs update |
| Settings | `Databases/Settings.tsx` | 🔄 Needs update |

### Servers (2 pages)
| Page | File | Status |
|------|------|--------|
| Servers List | `Servers/Index.tsx` | 🔄 Needs update |
| Server Detail | `Servers/Show.tsx` | 🔄 Needs update |

### Deployments (3 pages)
| Page | File | Status |
|------|------|--------|
| Deployments List | `Deployments/Index.tsx` | 🔄 Needs update |
| Deployment Detail | `Deployments/Show.tsx` | 🔄 Needs update |
| Build Logs | `Deployments/BuildLogs.tsx` | 🔄 Needs update |

### Domains (4 pages)
| Page | File | Status |
|------|------|--------|
| Domains List | `Domains/Index.tsx` | 🔄 Needs update |
| Domain Detail | `Domains/Show.tsx` | 🔄 Needs update |
| Add Domain | `Domains/Add.tsx` | 🔄 Needs update |
| Redirects | `Domains/Redirects.tsx` | 🔄 Needs update |

### Volumes (3 pages)
| Page | File | Status |
|------|------|--------|
| Volumes List | `Volumes/Index.tsx` | 🔄 Needs update |
| Volume Detail | `Volumes/Show.tsx` | 🔄 Needs update |
| Create Volume | `Volumes/Create.tsx` | 🔄 Needs update |

### Observability (5 pages)
| Page | File | Status |
|------|------|--------|
| Dashboard | `Observability/Index.tsx` | ✅ Good base |
| Metrics | `Observability/Metrics.tsx` | 🔄 Needs update |
| Logs | `Observability/Logs.tsx` | 🔄 Needs update |
| Traces | `Observability/Traces.tsx` | 🔄 Needs update |
| Alerts | `Observability/Alerts.tsx` | 🔄 Needs update |

### Activity (4 pages)
| Page | File | Status |
|------|------|--------|
| Activity List | `Activity/Index.tsx` | 🔄 Needs update |
| Activity Detail | `Activity/Show.tsx` | 🔄 Needs update |
| Project Activity | `Activity/ProjectActivity.tsx` | 🔄 Needs update |
| Timeline | `Activity/Timeline.tsx` | 🔄 Needs update |

### Notifications (3 pages)
| Page | File | Status |
|------|------|--------|
| Notifications | `Notifications/Index.tsx` | 🔄 Needs update |
| Detail | `Notifications/NotificationDetail.tsx` | 🔄 Needs update |
| Preferences | `Notifications/Preferences.tsx` | 🔄 Needs update |

### Templates (5 pages)
| Page | File | Status |
|------|------|--------|
| Gallery | `Templates/Index.tsx` | 🔄 Needs update |
| Template Detail | `Templates/Show.tsx` | 🔄 Needs update |
| Deploy | `Templates/Deploy.tsx` | 🔄 Needs update |
| Categories | `Templates/Categories.tsx` | 🔄 Needs update |
| Submit | `Templates/Submit.tsx` | 🔄 Needs update |

### Settings (21 pages)
| Page | File | Status |
|------|------|--------|
| Settings Home | `Settings/Index.tsx` | ✅ Good base |
| Account | `Settings/Account.tsx` | 🔄 Needs update |
| Security | `Settings/Security.tsx` | 🔄 Needs update |
| API Tokens | `Settings/APITokens.tsx` | 🔄 Needs update |
| Tokens | `Settings/Tokens.tsx` | 🔄 Needs update |
| Workspace | `Settings/Workspace.tsx` | 🔄 Needs update |
| Team | `Settings/Team.tsx` | 🔄 Needs update |
| Team Index | `Settings/Team/Index.tsx` | 🔄 Needs update |
| Team Invite | `Settings/Team/Invite.tsx` | 🔄 Needs update |
| Team Roles | `Settings/Team/Roles.tsx` | 🔄 Needs update |
| Team Activity | `Settings/Team/Activity.tsx` | 🔄 Needs update |
| Billing | `Settings/Billing.tsx` | 🔄 Needs update |
| Billing Index | `Settings/Billing/Index.tsx` | 🔄 Needs update |
| Plans | `Settings/Billing/Plans.tsx` | 🔄 Needs update |
| Invoices | `Settings/Billing/Invoices.tsx` | 🔄 Needs update |
| Payment Methods | `Settings/Billing/PaymentMethods.tsx` | 🔄 Needs update |
| Usage | `Settings/Billing/Usage.tsx` | 🔄 Needs update |
| Audit Log | `Settings/AuditLog.tsx` | 🔄 Needs update |
| Usage Stats | `Settings/Usage.tsx` | 🔄 Needs update |
| Integrations | `Settings/Integrations.tsx` | 🔄 Needs update |
| Member Detail | `Settings/Members/Show.tsx` | 🔄 Needs update |

### Cron Jobs (4 pages)
| Page | File | Status |
|------|------|--------|
| Cron List | `CronJobs/Index.tsx` | 🔄 Needs update |
| Cron Detail | `CronJobs/Show.tsx` | 🔄 Needs update |
| Create | `CronJobs/Create.tsx` | 🔄 Needs update |
| History | `ScheduledTasks/History.tsx` | 🔄 Needs update |

### Environment (2 pages)
| Page | File | Status |
|------|------|--------|
| Variables | `Environments/Variables.tsx` | 🔄 Needs update |
| Secrets | `Environments/Secrets.tsx` | 🔄 Needs update |

### CLI (2 pages)
| Page | File | Status |
|------|------|--------|
| Setup | `CLI/Setup.tsx` | 🔄 Needs update |
| Commands | `CLI/Commands.tsx` | 🔄 Needs update |

### Errors (4 pages)
| Page | File | Status |
|------|------|--------|
| 404 | `Errors/404.tsx` | 🔄 Needs update |
| 403 | `Errors/403.tsx` | 🔄 Needs update |
| 500 | `Errors/500.tsx` | 🔄 Needs update |
| Maintenance | `Errors/Maintenance.tsx` | 🔄 Needs update |

### Other (2 pages)
| Page | File | Status |
|------|------|--------|
| Webhooks | `Integrations/Webhooks.tsx` | 🔄 Needs update |
| Storage Backups | `Storage/Backups.tsx` | 🔄 Needs update |

---

## Total: ~105 pages

**Summary:**
- ✅ Good base: 5 pages
- 🔄 Needs update: ~100 pages

---

## Implementation Order

1. **Phase 1**: Design tokens в Tailwind (colors, shadows, radius)
2. **Phase 2**: Core components (Button, Card, Input, Badge)
3. **Phase 3**: Complex components (Dropdown, Modal, Tabs)
4. **Phase 4**: Status animations
5. **Phase 5**: Saturn branding (logo, name styling)
6. **Phase 6**: Canvas и node improvements
7. **Phase 7**: Page layouts (по группам из аудита)
8. **Phase 8**: Polish и accessibility audit

---

## Technical Notes

### Tailwind Config Updates
```js
// tailwind.config.js additions
theme: {
  extend: {
    backdropBlur: {
      xs: '2px',
    },
    animation: {
      'fade-in': 'fadeIn 0.3s ease-out',
      'pulse-slow': 'pulse 3s infinite',
      'shimmer': 'shimmer 2s infinite',
      'status-online': 'statusOnline 2s ease-in-out infinite',
      'status-deploying': 'statusDeploying 1s linear infinite',
      'status-error': 'statusError 1s ease-in-out infinite',
    },
    boxShadow: {
      'glow-primary': '0 0 20px rgba(99, 102, 241, 0.3)',
      'glow-success': '0 0 20px rgba(16, 185, 129, 0.3)',
      'glow-warning': '0 0 20px rgba(245, 158, 11, 0.3)',
      'glow-danger': '0 0 20px rgba(239, 68, 68, 0.3)',
    }
  }
}
```

### Performance
- Использовать `will-change` только для анимируемых элементов
- Предпочитать `transform` и `opacity` (GPU ускорение)
- Lazy load тяжёлых компонентов
- CSS custom properties для переключения тем

---

## Sources
- [Dark Mode and Glassmorphism Trends 2025](https://medium.com/@frameboxx81/dark-mode-and-glass-morphism-the-hottest-ui-trends-in-2025-864211446b54)
- [UI/UX Design Trends 2025](https://www.wearetenet.com/blog/ui-ux-design-trends)
- [SaaS Dashboard Design Trends](https://uitop.design/blog/design/top-dashboard-design-trends/)
- [Micro-interaction Examples](https://codemyui.com/tag/microinteractions/)
- [Railway UI Examples](https://nicelydone.club/apps/railway)
