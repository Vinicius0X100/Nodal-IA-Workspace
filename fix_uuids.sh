#!/bin/bash

# Fix Models
find app/Domain -name "*.php" | while read -r file; do
    # Replaces the HasUuids import with HasSecondaryUuid
    sed -i '' 's/use Illuminate\\Database\\Eloquent\\Concerns\\HasUuids;/use App\\Support\\Traits\\HasSecondaryUuid;/g' "$file"
    # Replaces HasUuids usage with HasSecondaryUuid
    sed -i '' 's/HasUuids/HasSecondaryUuid/g' "$file"
done

# Fix Migrations
find database/migrations -name "*.php" | while read -r file; do
    sed -i '' "s/\\\$table->uuid('id')->primary();/\\\$table->id();/g" "$file"
    sed -i '' "s/\\\$table->foreignUuid/\\\$table->foreignId/g" "$file"
done
