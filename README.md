# Setup

cp .env.example .env
docker compose build
docker compose up -d
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed
docker compose restart queue


# Commands

## Migrations

docker compose exec app php artisan migrate

## Seeders

docker compose exec app php artisan db:seed

## Queue worker

# already started by docker compose (service: queue)
# manual run:
docker compose exec queue php artisan queue:work --sleep=1 --tries=3 --timeout=90

## Tests

docker compose exec app php artisan test

# API

API: http://localhost:8000

POST /api/imports
202, async

GET /api/imports/{import}

GET /api/properties?check_in=&check_out=&guests=&city=&page=&per_page=
cheapest valid offer per property, SQL

POST /api/offers/{offer}/reservations
201, or 409 if unavailable/expired

# Import idempotency

unique(supplier_id, external_import_id)
duplicate request returns existing import, no second job
ProcessImport: ShouldBeUnique + afterCommit()

# Reservation concurrency

BEGIN
SELECT offer FOR UPDATE
check units > 0 and not expired
decrement units
insert reservation
COMMIT

Second request waits on lock, sees 0 units, gets 409.
client_reference is unique.