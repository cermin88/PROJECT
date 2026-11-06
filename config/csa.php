<?php

return [
    // Rows per page on every list table
    'per_page' => 15,

    // Reference figure only. There is NO individual revenue target:
    // official CSA revenue = qualified Award value (PO/LOA/official Award evidence).
    'revenue_target' => (float) env('CSA_REVENUE_TARGET', 8000000),
];
