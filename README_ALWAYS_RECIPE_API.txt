Every product needs a recipe (no "track stock" choice) - tamtam-api.
Extract into the ROOT of tamtam-api and say "Replace". These files REPLACE the track-stock versions.

NEW:      database/migrations/2026_11_23_000000_remove_track_stock_from_menu.php
REPLACED: app/Models/Product.php
          app/Services/ProductService.php
          app/Services/OrderService.php
          app/Http/Controllers/ProductController.php
          tests/Unit/ProductServiceTest.php
          tests/Unit/OrderServiceTest.php
          tests/Feature/ProductApiTest.php

The earlier 2026_11_22_000000_add_track_stock_to_menu.php migration:
  - already pushed or run anywhere?  KEEP it. The new migration removes the column afterwards.
  - never pushed?  you may delete it; the new migration does nothing harmlessly.

Then:  php artisan migrate   and   php artisan test
