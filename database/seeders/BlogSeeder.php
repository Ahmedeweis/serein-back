<?php

namespace Database\Seeders;

use App\Models\BlogCategory;
use App\Models\BlogTag;
use App\Models\Post;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class BlogSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Categories
        $categories = [
            'wellness' => ['en' => 'Wellness', 'ar' => 'العافية'],
            'natural-oils' => ['en' => 'Natural Oils', 'ar' => 'الزيوت الطبيعية'],
            'honey-guide' => ['en' => 'Honey Guide', 'ar' => 'دليل العسل'],
            'lifestyle' => ['en' => 'Lifestyle', 'ar' => 'نمط الحياة'],
            'health-science' => ['en' => 'Health Science', 'ar' => 'العلوم الصحية'],
        ];

        $categoryMap = [];
        foreach ($categories as $slug => $names) {
            $cat = BlogCategory::updateOrCreate(
                ['slug' => $slug],
                ['name_en' => $names['en'], 'name_ar' => $names['ar']]
            );
            $categoryMap[$slug] = $cat->id;
        }

        // 2. Exact Storefront Mock Articles
        $articles = [
            [
                'id' => 1,
                'slug' => 'benefits-of-raw-honey',
                'category_slug' => 'wellness',
                'title_en' => '5 Science-Backed Health Benefits of Raw Honey',
                'title_ar' => '٥ فوائد صحية مثبتة علمياً للعسل الخام',
                'excerpt_en' => "Discover why raw, unfiltered honey is one of nature's most powerful remedies — from immune support to digestive healing.",
                'excerpt_ar' => 'اكتشف لماذا يُعد العسل الخام وغير المُصفّى من أقوى العلاجات الطبيعية، من دعم المناعة إلى علاج الجهاز الهضمي.',
                'cover_image' => '/bloge.webp',
                'author_name' => 'Dr. Sara Ali',
                'author_avatar' => '/logo.png',
                'author_role_en' => 'Nutrition Scientist',
                'author_role_ar' => 'باحثة في التغذية',
                'is_featured' => true,
                'status' => 'published',
                'published_at' => '2026-08-20 09:00:00',
                'read_time_minutes' => 5,
                'views_count' => 342,
                'tags' => ['honey', 'health', 'wellness', 'nutrition'],
                'sections' => [
                    [
                        'heading_en' => '1. Rich in Antioxidants',
                        'heading_ar' => '١. غني بمضادات الأكسدة',
                        'body_en' => "Raw honey contains a variety of plant compounds called antioxidants that protect your body from inflammation and chronic disease. Unlike processed honey, it retains polyphenols and flavonoids that neutralize free radicals.",
                        'body_ar' => 'يحتوي العسل الخام على مجموعة متنوعة من المركبات النباتية تسمى مضادات الأكسدة التي تحمي جسمك من الالتهابات والأمراض المزمنة.',
                    ],
                    [
                        'heading_en' => '2. Soothes Sore Throats',
                        'heading_ar' => '٢. يُسكّن التهاب الحلق',
                        'body_en' => "A spoonful of raw honey has been used for centuries to calm sore throats and suppress coughs. Clinical studies show it is as effective as some over-the-counter cough medicines for nighttime symptom relief.",
                        'body_ar' => 'يُستخدم ملعقة من العسل الخام منذ قرون لتهدئة التهاب الحلق وكبح السعال. وتظهر الدراسات أنه فعال مثل بعض أدوية السعال المتوفرة دون وصفة.',
                    ],
                    [
                        'heading_en' => '3. Better for Blood Sugar',
                        'heading_ar' => '٣. أفضل لسكر الدم',
                        'body_en' => "Unlike refined sugar, raw honey has a gentler impact on blood glucose levels. Its natural mix of glucose and fructose provides steady energy without dramatic insulin spikes.",
                        'body_ar' => 'على عكس السكر المكرر، يترك العسل الخام تأثيراً ألطف على مستويات سكر الدم، مما يجعله خياراً أذكى للتحلية اليومية.',
                    ],
                    [
                        'heading_en' => '4. Heals Wounds & Burns',
                        'heading_ar' => '٤. يُعالج الجروح والحروق',
                        'body_en' => "Applied topically, medical-grade raw honey creates a protective barrier that fights bacterial infection and accelerates tissue regeneration.",
                        'body_ar' => 'عند وضعه موضعياً، يخلق العسل الطبيعي حاجزاً واقياً يحارب العدوى ويُسرع تجديد الأنسجة.',
                    ],
                    [
                        'heading_en' => '5. Supports Digestive Health',
                        'heading_ar' => '٥. يدعم صحة الجهاز الهضمي',
                        'body_en' => "Raw honey contains natural prebiotics and enzymes that nourish beneficial bacteria in your gut, improving digestion and nutrient absorption.",
                        'body_ar' => 'يحتوي العسل الخام على البريبايوتكس والإنزيمات التي تغذي البكتيريا النافعة في أمعائك، مما يحسن الهضم وامتصاص العناصر الغذائية.',
                    ],
                ],
            ],
            [
                'id' => 2,
                'slug' => 'black-seed-oil-uses',
                'category_slug' => 'natural-oils',
                'title_en' => 'Black Seed Oil: The Complete Guide to Uses & Benefits',
                'title_ar' => 'زيت حبة البركة: الدليل الشامل للاستخدامات والفوائد',
                'excerpt_en' => 'From glowing skin to stronger immunity, discover every proven way to use black seed oil in your daily routine.',
                'excerpt_ar' => 'من البشرة المشرقة إلى مناعة أقوى، اكتشف كل الطرق المثبتة لاستخدام زيت حبة البركة في روتينك اليومي.',
                'cover_image' => '/bloge.webp',
                'youtube_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                'author_name' => 'Khaled Mahmoud',
                'author_avatar' => '/logo.png',
                'author_role_en' => 'Traditional Herbalist',
                'author_role_ar' => 'أخصائي الأعشاب التقليدية',
                'is_featured' => false,
                'status' => 'published',
                'published_at' => '2026-08-12 09:00:00',
                'read_time_minutes' => 7,
                'views_count' => 218,
                'tags' => ['oils', 'wellness', 'black-seed', 'skincare'],
                'sections' => [
                    [
                        'heading_en' => 'A Remedy for Everything Except Death',
                        'heading_ar' => 'علاج لكل شيء إلا الموت',
                        'body_en' => "Nigella sativa, or black seed, has been revered for over 3,000 years across Eastern cultures. Modern science highlights thymoquinone as its main active healing compound.",
                        'body_ar' => 'قدِّر نبات الحبة السوداء، أو حبة البركة، منذ أكثر من ٣٠٠٠ عام. ويصفه التقليد الإسلامي بأنه شفاء من كل داء، والبحث العلمي الحديث يؤكد ذلك.',
                    ],
                    [
                        'heading_en' => 'Skin & Hair Benefits',
                        'heading_ar' => 'فوائد البشرة والشعر',
                        'body_en' => "Mix a few drops with your daily moisturizer for deep hydration, or gently massage into your scalp to strengthen roots and combat dry dandruff.",
                        'body_ar' => 'اخلطي بضع قطرات مع المرطب الخاص بكِ للترطيب، أو دلكي فروة الرأس لتقوية بصيلات الشعر وتقليل القشرة.',
                    ],
                    [
                        'heading_en' => 'Immune Support Protocol',
                        'heading_ar' => 'بروتوكول دعم المناعة',
                        'body_en' => "Take one teaspoon daily blended with raw honey or lukewarm water on an empty stomach to strengthen your body's immune defenses.",
                        'body_ar' => 'تناول ملعقة صغيرة يومياً مع العسل أو الماء الدافئ لتفعيل استجابة مناعتك بشكل طبيعي.',
                    ],
                ],
            ],
            [
                'id' => 3,
                'slug' => 'manuka-honey-vs-raw-honey',
                'category_slug' => 'honey-guide',
                'title_en' => 'Manuka vs. Raw Wildflower Honey: Which Is Better?',
                'title_ar' => 'عسل المانوكا مقابل عسل الزهور البري: أيهما أفضل؟',
                'excerpt_en' => 'We break down the differences in antibacterial power, flavor profile, nutritional value, and price between these two honey superstars.',
                'excerpt_ar' => 'نفصّل الفروقات في القوة المضادة للبكتيريا، ونكهة، والقيمة الغذائية، والسعر بين هذين النجمين من العسل.',
                'cover_image' => '/bloge.webp',
                'author_name' => 'Dr. Sara Ali',
                'author_avatar' => '/logo.png',
                'author_role_en' => 'Nutrition Scientist',
                'author_role_ar' => 'باحثة في التغذية',
                'is_featured' => false,
                'status' => 'published',
                'published_at' => '2026-08-05 09:00:00',
                'read_time_minutes' => 6,
                'views_count' => 195,
                'tags' => ['honey', 'guide', 'comparison', 'manuka'],
                'sections' => [
                    [
                        'heading_en' => 'The Origins',
                        'heading_ar' => 'الأصول',
                        'body_en' => "Manuka comes exclusively from New Zealand's Leptospermum scoparium bush, while raw wildflower honey is a multi-floral blend harvested from diverse wildflowers.",
                        'body_ar' => 'يأتي عسل المانوكا حصراً من شجيرة المانوكا النيوزيلندية، بينما عسل الزهور البري الخام مزيج متعدد الأزهار يتم جمعه من أزهار محلية متنوعة.',
                    ],
                    [
                        'heading_en' => 'Antibacterial Activity',
                        'heading_ar' => 'النشاط المضاد للبكتيريا',
                        'body_en' => "Manuka's UMF rating measures its unique non-peroxide antibacterial potency. Certified UMF 10+ is clinically proven for therapeutic wound care and gut health.",
                        'body_ar' => 'يقيس تصنيف UMF لعسل المانوكا نشاطه الفريد المضاد للبكتيريا. والمُصدَّر بدرجة UMF 10+ مثبت سريرياً لعناية الجروح.',
                    ],
                    [
                        'heading_en' => 'Best Uses',
                        'heading_ar' => 'أفضل الاستخدامات',
                        'body_en' => "Choose Manuka for targeted medical and restorative therapies. Choose raw wildflower for everyday tea sweetening, breakfast oats, and daily wellness.",
                        'body_ar' => 'اختر المانوكا للتطبيقات العلاجية. واختر الزهور البري الخام للتحلية اليومية والخبز والعافية العامة.',
                    ],
                ],
            ],
            [
                'id' => 4,
                'slug' => 'morning-honey-routine',
                'category_slug' => 'lifestyle',
                'title_en' => 'The 5-Minute Morning Honey Routine That Changed My Energy',
                'title_ar' => 'روتين العسل الصباحي الذي يستغرق ٥ دقائق وغيّر طاقتي',
                'excerpt_en' => 'A nutritionist shares her simple daily ritual of raw honey, warm water, and lemon for sustained energy without the mid-morning crash.',
                'excerpt_ar' => 'أخصائية تغذية تشارك طقسها اليومي البسيط من العسل الخام والماء الدافئ والليمون للحصول على طاقة مستمرة دون هبوط منتصف الصباح.',
                'cover_image' => '/bloge.webp',
                'author_name' => 'Lina Hassan',
                'author_avatar' => '/logo.png',
                'author_role_en' => 'Wellness Coach',
                'author_role_ar' => 'مدربة عافية',
                'is_featured' => false,
                'status' => 'published',
                'published_at' => '2026-07-28 09:00:00',
                'read_time_minutes' => 4,
                'views_count' => 164,
                'tags' => ['honey', 'routine', 'energy', 'morning'],
                'sections' => [
                    [
                        'heading_en' => 'Why It Works',
                        'heading_ar' => 'لماذا يعمل؟',
                        'body_en' => "The synergistic combination of bioavailable fructose, active enzymes, and natural vitamin C creates sustained cellular hydration and energy.",
                        'body_ar' => 'إن المزيج من السكريات الطبيعية والإنزيمات وفيتامين C يُخلق ملف طاقة بطيئة الإطلاق يُبقيك مستقراً لساعات.',
                    ],
                    [
                        'heading_en' => 'The Exact Recipe',
                        'heading_ar' => 'الوصفة الدقيقة',
                        'body_en' => "350ml lukewarm (not boiling) mineral water + 1 generous teaspoon of raw clover honey + fresh juice of 1/4 lemon. Sip slowly 30 minutes before breakfast.",
                        'body_ar' => '٣٥٠ مل ماء دافئ (وليس مغلياً) + ملعقة صغيرة عسل خام + عصير ربع ليمونة. قلّبي وتناوليها على معدة فارغة قبل الفطور بـ٣٠ دقيقة.',
                    ],
                ],
            ],
            [
                'id' => 5,
                'slug' => 'argan-oil-skin-care',
                'category_slug' => 'natural-oils',
                'title_en' => 'Argan Oil: Your Complete Skincare Routine in One Bottle',
                'title_ar' => 'زيت الأرغان: روتين العناية بالبشرة الكامل في زجاجة واحدة',
                'excerpt_en' => 'From cleanser to moisturizer to serum — why this Moroccan "liquid gold" might be the only skincare product you actually need.',
                'excerpt_ar' => 'من المنظف إلى المرطب إلى السيروم — لماذا قد يكون "الذهب السائل" المغربي هو منتج العناية الوحيد الذي تحتاجه فعلاً.',
                'cover_image' => '/bloge.webp',
                'author_name' => 'Khaled Mahmoud',
                'author_avatar' => '/logo.png',
                'author_role_en' => 'Traditional Herbalist',
                'author_role_ar' => 'أخصائي الأعشاب التقليدية',
                'is_featured' => false,
                'status' => 'published',
                'published_at' => '2026-07-20 09:00:00',
                'read_time_minutes' => 6,
                'views_count' => 143,
                'tags' => ['oils', 'skincare', 'argan', 'beauty'],
                'sections' => [
                    [
                        'heading_en' => "Morocco's Ancient Secret",
                        'heading_ar' => 'سر المغرب القديم',
                        'body_en' => "Cold-pressed from the seeds of the native Argania spinosa tree, pure Moroccan Argan oil contains intense concentrations of vitamin E and omega-6 fatty acids.",
                        'body_ar' => 'مستخرج على البارد من ثمار شجيرة الأرغان، ويُعد هذا الزيت أساس جمال البربر منذ قرون لاحتوائه على فيتامين E وأحماض أوميغا الدهنية.',
                    ],
                    [
                        'heading_en' => 'How to Apply',
                        'heading_ar' => 'طريقة الاستخدام',
                        'body_en' => "After cleansing, gently press 2-3 warm drops into damp facial skin. It locks in cellular moisture without clogging pores.",
                        'body_ar' => 'ضعي ٢-٣ قطرات على بشرة مبللة بعد التنظيف. دلكي برفق لقفل الرطوبة دون انسداد المسام.',
                    ],
                ],
            ],
            [
                'id' => 6,
                'slug' => 'honey-for-diabetics',
                'category_slug' => 'health-science',
                'title_en' => "Can Diabetics Eat Honey? A Doctor's Honest Answer",
                'title_ar' => 'هل يستطيع مرضى السكري تناول العسل؟ إجابة طبيب صريحة',
                'excerpt_en' => 'The surprising truth about glycemic index, portion control, and which honey varieties raise blood sugar the least.',
                'excerpt_ar' => 'الحقيقة المُفاجئة حول المؤشر الجلايسيمي، والتحكم في الكمية، وأي أنواع العسل ترفع سكر الدم أقل.',
                'cover_image' => '/bloge.webp',
                'author_name' => 'Dr. Omar Farouk',
                'author_avatar' => '/logo.png',
                'author_role_en' => 'Endocrinologist',
                'author_role_ar' => 'طبيب غدد صماء وسكري',
                'is_featured' => false,
                'status' => 'published',
                'published_at' => '2026-07-15 09:00:00',
                'read_time_minutes' => 5,
                'views_count' => 289,
                'tags' => ['honey', 'health', 'diabetes', 'science'],
                'sections' => [
                    [
                        'heading_en' => 'The Glycemic Index Truth',
                        'heading_ar' => 'حقيقة المؤشر الجلايسيمي',
                        'body_en' => "Raw unpasteurized honey typically scores 50-58 on the GI scale — lower than table sucrose (65) and significantly lower than high-fructose corn syrup (87).",
                        'body_ar' => 'يتراوح متوسط العسل الخام حول ٥٠-٥٨ على مقياس المؤشر الجلايسيمي — أي أقل من السكر الأبيض (٦٥) وأقل بكثير من شراب الفركتوز المرتفع (٨٧).',
                    ],
                    [
                        'heading_en' => 'Portion Control Is Essential',
                        'heading_ar' => 'الكمية هي كل شيء',
                        'body_en' => "One teaspoon contains approximately 5 grams of total carbohydrates. Diabetics should treat authentic honey like concentrated medicine: measured and enjoyed alongside dietary fiber.",
                        'body_ar' => 'الملعقة الصغيرة تعادل حوالي ٢٠ سعرة حرارية و٥ غرامات سكر. وفي إطار وجبة متوازنة، يجب معاملته بحرص وقياس دقيق.',
                    ],
                ],
            ],
        ];

        foreach ($articles as $art) {
            $catId = $categoryMap[$art['category_slug']] ?? null;

            // Compile clean HTML for Storefront rendering
            $contentEn = Post::compileHtmlFromSections($art['sections'], 'en');
            $contentAr = Post::compileHtmlFromSections($art['sections'], 'ar');

            $post = Post::updateOrCreate(
                ['slug' => $art['slug']],
                [
                    'blog_category_id'  => $catId,
                    'title_en'          => $art['title_en'],
                    'title_ar'          => $art['title_ar'],
                    'excerpt_en'        => $art['excerpt_en'],
                    'excerpt_ar'        => $art['excerpt_ar'],
                    'sections'          => $art['sections'],
                    'content_en'        => $contentEn,
                    'content_ar'        => $contentAr,
                    'cover_image'       => $art['cover_image'],
                    'youtube_url'       => $art['youtube_url'] ?? null,
                    'author_name'       => $art['author_name'],
                    'author_avatar'     => $art['author_avatar'],
                    'author_role_en'    => $art['author_role_en'],
                    'author_role_ar'    => $art['author_role_ar'],
                    'is_featured'       => $art['is_featured'],
                    'status'            => $art['status'],
                    'published_at'      => $art['published_at'],
                    'read_time_minutes' => $art['read_time_minutes'],
                    'views_count'       => $art['views_count'],
                ]
            );

            // Sync Tags
            $tagIds = [];
            foreach ($art['tags'] as $tagName) {
                $tag = BlogTag::firstOrCreate(
                    ['slug' => Str::slug($tagName)],
                    ['name' => $tagName]
                );
                $tagIds[] = $tag->id;
            }
            $post->tags()->sync($tagIds);
        }
    }
}
