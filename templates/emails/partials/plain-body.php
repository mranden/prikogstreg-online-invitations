<?php

declare(strict_types=1);

echo esc_html( (string) ( $context['invitation_url'] ?? $context['account_url'] ?? '' ) );
