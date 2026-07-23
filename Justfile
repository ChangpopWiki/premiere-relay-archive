set positional-arguments := true

default:
    just --list

up *args="--detach":
    docker compose up {{args}}

down *args:
    docker compose down {{args}}

build *args:
    docker compose build {{args}}

logs *args:
    docker compose logs {{args}}

exec *args:
    docker compose exec "$@"

backfill-data *args:
    just exec web php ./scripts/backfill_data.php "$@"