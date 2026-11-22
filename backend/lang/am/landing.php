<?php

// Amharic plan lines for Landing 2's prices (owner, 2026-11-22). Only the "pricing" part:
// Landing 1 is English only. A first draft: NEEDS A NATIVE SPEAKER'S REVIEW.
return [
    'pricing' => [
        'free' => 'ነፃ',
        'per' => ['month' => '/ በወር', 'year' => '/ በዓመት'],
        'no_expiry' => 'ለዘላለም',
        'whatsapp' => 'በWhatsApp ያግኙን',
        'email' => 'ያግኙን',
        'whatsapp_text' => 'ሰላም፣ ለምግብ ቤቴ የ:plan እቅድ እፈልጋለሁ።',
        'yearly' => 'ወይም :price / በዓመት (:months ወር ነፃ)',
        'everything' => 'በ:plan ያለው ሁሉ፣ እንዲሁም፦',
        'limit' => [
            'items' => 'እስከ :n ምግቦች',
            'photos' => 'እስከ :n ፎቶዎች',
            'menus' => 'እስከ :n ሜኑዎች',
            'branches' => '{1} 1 ቅርንጫፍ|[2,*] እስከ :n ቅርንጫፎች',
            'tables' => 'እስከ :n የጠረጴዛ QR ኮዶች',
            'members' => '{1} 1 የቡድን አባል|[2,*] እስከ :n የቡድን አባላት',
            'languages' => '{1} 1 ቋንቋ|[2,*] እስከ :n ቋንቋዎች',
        ],
        'unlimited' => [
            'items' => 'ያልተገደቡ ምግቦች፣ ሜኑዎችና QR ኮዶች',
            'photos' => 'ያልተገደቡ ፎቶዎች',
            'branches' => 'ያልተገደቡ ቅርንጫፎች',
        ],
        'feature' => [
            'styles' => '{1} 1 የሜኑ ስታይል|[2,3] :n የሜኑ ስታይሎች|[4,*] ሁሉም :n የሜኑ ስታይሎች',
            'pdf_menu' => 'የPDF ሜኑ',
            'print' => 'ፖስተሮች፣ የጠረጴዛ ካርዶችና ተለጣፊዎች',
            'order_list' => '«የእኔ ትዕዛዝ» ዝርዝር',
            'promotions' => 'ልዩ ቅናሾችና ማስታወቂያዎች',
            'price_periods' => 'የደስታ ሰዓትና የምሳ ሰዓት ዋጋዎች',
            'insights' => 'ትንታኔና ወርሃዊ ሪፖርት',
            'branches' => 'ብዙ ቅርንጫፎች',
            'import_export' => 'ማስገባትና ማውጣት (በስፕሬድሺት)',
            'remove_branding' => 'የ«:name» ስም መስመር ከሜኑው ይወገዳል',
        ],
    ],
];
