# portal-web

Laravel + Vue 3 package for:

- authentication
- member center
- profile CRUD and publish
- whitelist and blacklist management
- subscription and billing presentation
- query log and statistics display

## Package layout

- `app/`, `routes/`, `database/`: Laravel API and domain services
- `web/`: Vue 3 + Vite frontend source
- `dist/`: frontend production build output

## Local development

- API: `php artisan serve --host=0.0.0.0 --port=8080`
- Frontend: `npm run dev`
- Frontend build: `npm run build`

## Planned modules

- `app/Domain/Auth`
- `app/Domain/Profile`
- `app/Domain/Rule`
- `app/Domain/Device`
- `app/Domain/Plan`
- `app/Domain/Billing`
- `app/Domain/Usage`
- `app/Domain/Audit`
- `app/Infrastructure/DnsConsole`

## First APIs to implement

- `POST /api/v1/public/auth/register`
- `POST /api/v1/public/auth/login`
- `GET /api/v1/member/me`
- `GET|POST /api/v1/member/profiles`
- `PUT|DELETE /api/v1/member/profiles/{profile_id}`
- `GET|POST /api/v1/member/profiles/{profile_id}/rules`
- `POST /api/v1/member/profiles/{profile_id}/publish`
- `GET /api/v1/member/profiles/{profile_id}/logs`
