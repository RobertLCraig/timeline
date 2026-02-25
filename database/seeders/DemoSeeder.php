<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Event;
use App\Models\EventCategory;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('email', 'admin@family.com')->firstOrFail();

        // Create (or reuse) the demo group
        $group = Group::updateOrCreate(
            ['slug' => 'demo'],
            [
                'name'        => 'The Johnson Family',
                'description' => 'A sample family timeline showcasing all event types. This group is open to the public — browse our family memories!',
                'slug'        => 'demo',
                'created_by'  => $admin->id,
            ]
        );

        // Ensure admin is the owner member
        GroupMember::updateOrCreate(
            ['group_id' => $group->id, 'user_id' => $admin->id],
            ['role' => 'owner', 'joined_at' => now()]
        );

        // Set admin's active group if not set
        if (!$admin->active_group_id) {
            $admin->update(['active_group_id' => $group->id]);
        }

        // Enrol ALL existing non-admin users as members of the demo group
        \App\Models\User::where('id', '!=', $admin->id)->each(function ($u) use ($group) {
            GroupMember::updateOrCreate(
                ['group_id' => $group->id, 'user_id' => $u->id],
                ['role' => 'member', 'joined_at' => now()]
            );

            if (!$u->active_group_id) {
                $u->update(['active_group_id' => $group->id]);
            }
        });

        // Helper: look up category ID by name
        $cat = fn(string $name) => EventCategory::where('name', $name)->value('id');

        // Map event title → demo image URL (only titles that have a matching asset)
        $imageMap = [
            'Robert & Susan Get Married'                     => '/assets/demo/1982-06-12__Robert_Susan_Get_Married.png',
            'We Moved to Portland'                           => '/assets/demo/1982-08-01__We_Moved_to_Portland.png',
            'Emma Johnson Is Born'                           => '/assets/demo/1985-03-15__Emma_Johnson_Is_Born.png',
            'Robert & Susan — 5 Years'                       => '/assets/demo/1987-06-12__Robert_Susan_5_Years.png',
            'Liam Johnson Arrives'                           => '/assets/demo/1988-07-22__Liam_Johnson_Arrives.png',
            'Robert Promoted to Regional Manager'            => '/assets/demo/1990-01-15__Robert_Promoted_to_Regional_Manager.png',
            'First Johnson Family Reunion'                   => '/assets/demo/1990-08-10__First_Johnson_Family_Reunion.png',
            'Robert & Susan — 10 Years'                      => '/assets/demo/1992-06-12__Robert_Susan_10_Years.png',
            'Disney World: The Great Family Vacation'        => '/assets/demo/1994-07-04__Disney_World_The_Great_Family_Vacation.png',
            'New Home in Austin'                             => '/assets/demo/1995-04-15__New_Home_in_Austin.png',
            'Susan Opens "The Bread Basket" Bakery'          => '/assets/demo/1997-03-01__Susan_Opens_The_Bread_Basket_Bakery.png',
            'Robert Turns 50'                                => '/assets/demo/1998-10-03__Robert_Turns_50.png',
            "Grandpa Henry's Open-Heart Surgery"             => '/assets/demo/1999-11-12__Grandpa_Henrys_Open-Heart_Surgery.png',
            'European Summer Adventure'                      => '/assets/demo/2000-07-15__European_Summer_Adventure.png',
            "Susan's 50th Birthday"                          => '/assets/demo/2001-05-14__Susans_50th_Birthday.png',
            'We Adopted Max (the Dog)'                       => '/assets/demo/2002-12-25__We_Adopted_Max_the_Dog.png',
            'Emma Graduates High School'                     => '/assets/demo/2003-06-15__Emma_Graduates_High_School.png',
            'Emma Moves into College Dorms'                  => '/assets/demo/2003-08-25__Emma_Moves_into_College_Dorms.png',
            'Grandma Eleanor Turns 80'                       => '/assets/demo/2005-03-22__Grandma_Eleanor_Turns_80.png',
            'Liam Graduates High School'                     => '/assets/demo/2006-06-15__Liam_Graduates_High_School.png',
            "Emma's College Graduation — BSc Marketing"      => '/assets/demo/2007-05-20__Emmas_College_Graduation_BSc_Marketing.png',
            'Silver Anniversary — 25 Years!'                 => '/assets/demo/2007-06-12__Silver_Anniversary_25_Years.png',
            'Emma Lands First Marketing Role'                => '/assets/demo/2007-09-01__Emma_Lands_First_Marketing_Role.png',
            'Emma & Tom — 1 Year Together'                   => '/assets/demo/2008-09-20__Emma_Tom_1_Year_Together.png',
            "Emma & Tom's Wedding"                           => '/assets/demo/2009-08-15__Emma_Toms_Wedding.png',
            "Emma & Tom's Hawaiian Honeymoon"                => '/assets/demo/2009-08-22__Emma_Toms_Hawaiian_Honeymoon.png',
            'Liam Relocates to Seattle'                      => '/assets/demo/2010-02-14__Liam_Relocates_to_Seattle.png',
            'Emma Runs Her First Marathon'                   => '/assets/demo/2011-10-09__Emma_Runs_Her_First_Marathon.png',
            'Baby Sophia Makes Four'                         => '/assets/demo/2012-11-04__Baby_Sophia_Makes_Four.png',
            "Liam's Master's Degree in Computer Science"     => '/assets/demo/2013-05-18__Liams_Masters_Degree_in_Computer_Science.png',
            'Liam & Kate Get Married'                        => '/assets/demo/2014-05-24__Liam_Kate_Get_Married.png',
            'Liam Co-founds a Tech Startup'                  => '/assets/demo/2014-10-15__Liam_Co-founds_a_Tech_Startup.png',
            'The Johnson Family Newsletter — Vol. 1'         => '/assets/demo/2015-02-01__The_Johnson_Family_Newsletter_Vol_1.png',
            "Emma's 30th Birthday"                           => '/assets/demo/2015-03-15__Emmas_30th_Birthday.png',
            'Robert Retires After 35 Years'                  => '/assets/demo/2015-06-01__Robert_Retires_After_35_Years.png',
            'Noah Joins the Family'                          => '/assets/demo/2015-06-30__Noah_Joins_the_Family.png',
            "Noah's First Steps"                             => '/assets/demo/2016-04-03__Noahs_First_Steps.png',
            'Japan: Cherry Blossom Season'                   => '/assets/demo/2016-04-10__Japan_Cherry_Blossom_Season.png',
            "Sophia's First Day of Kindergarten"             => '/assets/demo/2017-09-05__Sophias_First_Day_of_Kindergarten.png',
            "Cousin Maria's Wedding"                         => '/assets/demo/2018-09-08__Cousin_Marias_Wedding.png',
            'Liam Completes His First Triathlon'             => '/assets/demo/2019-07-21__Liam_Completes_His_First_Triathlon.png',
            'Staying Connected During the Pandemic'          => '/assets/demo/2020-04-15__Staying_Connected_During_the_Pandemic.png',
            'Robert & Susan — Ruby Anniversary (40 Years!)' => '/assets/demo/2022-06-12__Robert_Susan_Ruby_Anniversary_40_Years.png',
            'Pacific Coast Highway Road Trip'                => '/assets/demo/2022-08-14__Pacific_Coast_Highway_Road_Trip.png',
            'Family Wellness Challenge'                      => '/assets/demo/2023-01-01__Family_Wellness_Challenge.png',
            'Johnson Family Time Capsule'                    => '/assets/demo/2024-01-01__Johnson_Family_Time_Capsule.png',
        ];

        $events = [
            // ── BIRTH (4) ──────────────────────────────────────────────
            [
                'title'       => 'Emma Johnson Is Born',
                'description' => 'At 6:42 AM on a crisp spring morning, Emma entered the world weighing 7 lbs 4 oz. The whole family rushed to Portland General to welcome her.',
                'event_date'  => '1985-03-15',
                'category'    => 'Birth',
            ],
            [
                'title'       => 'Liam Johnson Arrives',
                'description' => 'Little Liam joined the family on a hot July afternoon. Emma, already three, insisted on holding him immediately — and hasn\'t stopped looking out for him since.',
                'event_date'  => '1988-07-22',
                'category'    => 'Birth',
            ],
            [
                'title'       => 'Baby Sophia Makes Four',
                'description' => 'Emma and Tom welcomed their daughter Sophia into the world. Grandma flew in from Denver and Grandpa cried happy tears in the waiting room.',
                'event_date'  => '2012-11-04',
                'category'    => 'Birth',
            ],
            [
                'title'       => 'Noah Joins the Family',
                'description' => 'Liam and Kate\'s son Noah arrived right on his due date — a rare feat. Kate said he was already reliable before he was born.',
                'event_date'  => '2015-06-30',
                'category'    => 'Birth',
            ],

            // ── MOVE (4) ───────────────────────────────────────────────
            [
                'title'       => 'We Moved to Portland',
                'description' => 'Robert accepted a job offer and the whole family packed up and relocated from Sacramento to Portland. We fell in love with the city immediately.',
                'event_date'  => '1982-08-01',
                'category'    => 'Move',
            ],
            [
                'title'       => 'New Home in Austin',
                'description' => 'After 13 years in Portland, the family moved to a bigger house in Austin. The kids were heartbroken about leaving friends but quickly fell in love with Texas.',
                'event_date'  => '1995-04-15',
                'category'    => 'Move',
            ],
            [
                'title'       => 'Emma Moves into College Dorms',
                'description' => 'We packed Emma\'s entire life into two car loads and set her up at the University of Oregon. The drive home felt very quiet.',
                'event_date'  => '2003-08-25',
                'category'    => 'Move',
            ],
            [
                'title'       => 'Liam Relocates to Seattle',
                'description' => 'Liam landed his dream job in Seattle and made the leap. He calls it rainy but wonderful. We call it an excuse to visit the Pacific Northwest.',
                'event_date'  => '2010-02-14',
                'category'    => 'Move',
            ],

            // ── ANNIVERSARY (5) ────────────────────────────────────────
            [
                'title'       => 'Robert & Susan — 5 Years',
                'description' => 'The couple celebrated with a weekend trip to the Oregon coast and renewed their vows on the beach. Emma was two and kept throwing sand at the officiant.',
                'event_date'  => '1987-06-12',
                'category'    => 'Anniversary',
            ],
            [
                'title'       => 'Robert & Susan — 10 Years',
                'description' => 'A decade of marriage and growing family. The kids made homemade cards and Susan framed every single one.',
                'event_date'  => '1992-06-12',
                'category'    => 'Anniversary',
            ],
            [
                'title'       => 'Silver Anniversary — 25 Years!',
                'description' => 'The whole family gathered for a surprise party. Robert had no idea until he walked through the door to a room of 60 people. He cried. We all cried.',
                'event_date'  => '2007-06-12',
                'category'    => 'Anniversary',
            ],
            [
                'title'       => 'Emma & Tom — 1 Year Together',
                'description' => 'Emma and Tom celebrated their first anniversary with a trip back to where they met — a small café in Eugene. They\'ve since made it an annual tradition.',
                'event_date'  => '2008-09-20',
                'category'    => 'Anniversary',
            ],
            [
                'title'       => 'Robert & Susan — Ruby Anniversary (40 Years!)',
                'description' => 'Forty years of partnership, friendship, and love. The grandkids performed a short play about their love story. The script needed some editing but the sentiment was perfect.',
                'event_date'  => '2022-06-12',
                'category'    => 'Anniversary',
            ],

            // ── GRADUATION (5) ─────────────────────────────────────────
            [
                'title'       => 'Emma Graduates High School',
                'description' => 'Valedictorian of her class. Her speech quoted Carl Sagan and made Susan ugly-cry. Robert kept the program in his wallet for years.',
                'event_date'  => '2003-06-15',
                'category'    => 'Graduation',
            ],
            [
                'title'       => 'Liam Graduates High School',
                'description' => 'Liam crossed the stage to the theme from Star Wars — he negotiated that with the principal himself. Somehow it worked.',
                'event_date'  => '2006-06-15',
                'category'    => 'Graduation',
            ],
            [
                'title'       => "Emma's College Graduation — BSc Marketing",
                'description' => 'Four years flew by. Emma graduated with honors from the University of Oregon. Job offer already in hand before the ceremony ended.',
                'event_date'  => '2007-05-20',
                'category'    => 'Graduation',
            ],
            [
                'title'       => "Liam's Master's Degree in Computer Science",
                'description' => 'Liam completed his MS at UW Seattle with a focus on machine learning. His thesis was on something none of us understood, but we cheered very loudly.',
                'event_date'  => '2013-05-18',
                'category'    => 'Graduation',
            ],
            [
                'title'       => "Sophia's First Day of Kindergarten",
                'description' => 'Technically not a graduation, but it felt like one. She walked in with her backpack half her size and didn\'t look back once. Emma was a wreck.',
                'event_date'  => '2017-09-05',
                'category'    => 'Graduation',
            ],

            // ── MILESTONE (5) ──────────────────────────────────────────
            [
                'title'       => "Robert Turns 50",
                'description' => 'Half a century of Robert! The family threw a surprise garden party with a slideshow of his most embarrassing photos from the 70s and 80s. He loved it.',
                'event_date'  => '1998-10-03',
                'category'    => 'Milestone',
            ],
            [
                'title'       => "Susan's 50th Birthday",
                'description' => 'Susan always said she\'d never have a big party for 50. We ignored that. The backyard was full of friends, family, and an inexplicable number of flamingo decorations.',
                'event_date'  => '2001-05-14',
                'category'    => 'Milestone',
            ],
            [
                'title'       => "Grandma Eleanor Turns 80",
                'description' => 'Four generations gathered to celebrate Eleanor\'s 80th. She danced to Elvis, beat everyone at cards, and stayed up until midnight. Goals.',
                'event_date'  => '2005-03-22',
                'category'    => 'Milestone',
            ],
            [
                'title'       => "Emma's 30th Birthday",
                'description' => 'Emma claimed she was "fine" about turning 30. The three-tiered cake, the weekend trip to Napa, and the tearful speech suggested otherwise. Beautifully otherwise.',
                'event_date'  => '2015-03-15',
                'category'    => 'Milestone',
            ],
            [
                'title'       => "Noah's First Steps",
                'description' => 'In the living room, with the whole family watching, Noah let go of the coffee table and took four wobbly steps toward Grandma. Everyone screamed.',
                'event_date'  => '2016-04-03',
                'category'    => 'Milestone',
            ],

            // ── WEDDING (4) ────────────────────────────────────────────
            [
                'title'       => 'Robert & Susan Get Married',
                'description' => 'A sunny June afternoon in Sacramento. Susan wore her grandmother\'s lace. Robert wrote his own vows and only cried twice. Their first dance was to Fleetwood Mac.',
                'event_date'  => '1982-06-12',
                'category'    => 'Wedding',
            ],
            [
                'title'       => 'Emma & Tom\'s Wedding',
                'description' => 'A beautiful outdoor ceremony in Sonoma Valley. 85 guests, a string quartet, and the most elaborate floral arch ever constructed by someone\'s Uncle Dave.',
                'event_date'  => '2009-08-15',
                'category'    => 'Wedding',
            ],
            [
                'title'       => 'Liam & Kate Get Married',
                'description' => 'Liam and Kate tied the knot at a lakeside venue in the Cascades. It rained for exactly 20 minutes, stopped, and the photos turned out perfect.',
                'event_date'  => '2014-05-24',
                'category'    => 'Wedding',
            ],
            [
                'title'       => "Cousin Maria's Wedding",
                'description' => 'Maria married her partner James in a vineyard in Willamette Valley. The whole Johnson crew made the trip and took over an entire inn for the weekend.',
                'event_date'  => '2018-09-08',
                'category'    => 'Wedding',
            ],

            // ── TRAVEL (5) ─────────────────────────────────────────────
            [
                'title'       => 'Disney World: The Great Family Vacation',
                'description' => 'Seven days in Orlando. Emma rode Space Mountain 11 times. Liam was too scared to go twice. Robert queued 90 minutes for a churro. 10/10 experience.',
                'event_date'  => '1994-07-04',
                'category'    => 'Travel',
            ],
            [
                'title'       => 'European Summer Adventure',
                'description' => 'Three weeks, four countries — France, Italy, Switzerland, and Spain. We ate our way across the continent and the kids are still talking about the gelato in Florence.',
                'event_date'  => '2000-07-15',
                'category'    => 'Travel',
            ],
            [
                'title'       => 'Emma & Tom\'s Hawaiian Honeymoon',
                'description' => 'Two weeks in Maui and Kauai. Tom proposed the cliff dive at Na Pali Coast. Emma agreed. Susan has not fully recovered.',
                'event_date'  => '2009-08-22',
                'category'    => 'Travel',
            ],
            [
                'title'       => 'Japan: Cherry Blossom Season',
                'description' => 'Robert, Susan, Emma, Tom and the kids visited Tokyo and Kyoto during hanami. We walked under full sakura bloom in Maruyama Park and nobody spoke for a full minute.',
                'event_date'  => '2016-04-10',
                'category'    => 'Travel',
            ],
            [
                'title'       => 'Pacific Coast Highway Road Trip',
                'description' => 'Three generations, two cars, one legendary road trip from Seattle to San Francisco. We stopped at every scenic overlook. The playlist was fiercely debated.',
                'event_date'  => '2022-08-14',
                'category'    => 'Travel',
            ],

            // ── CAREER (5) ─────────────────────────────────────────────
            [
                'title'       => 'Robert Promoted to Regional Manager',
                'description' => 'After seven years of hard work at the firm, Robert\'s promotion came through. He celebrated with a modest dinner and Susan threw an immodest party the next weekend.',
                'event_date'  => '1990-01-15',
                'category'    => 'Career',
            ],
            [
                'title'       => 'Susan Opens "The Bread Basket" Bakery',
                'description' => 'Susan turned her passion for baking into a business. The bakery opened to a queue down the block on day one. It\'s been fully booked on weekends ever since.',
                'event_date'  => '1997-03-01',
                'category'    => 'Career',
            ],
            [
                'title'       => 'Emma Lands First Marketing Role',
                'description' => 'Fresh out of university and straight into a fast-paced agency in Portland. She negotiated a higher starting salary than they offered. We weren\'t surprised.',
                'event_date'  => '2007-09-01',
                'category'    => 'Career',
            ],
            [
                'title'       => 'Liam Co-founds a Tech Startup',
                'description' => 'Liam and two colleagues from UW launched a machine learning startup in Seattle. The pitch deck was presented at the kitchen table. The first round closed six months later.',
                'event_date'  => '2014-10-15',
                'category'    => 'Career',
            ],
            [
                'title'       => 'Robert Retires After 35 Years',
                'description' => 'Robert hung up his briefcase after 35 years at the same company. His team threw him a party, his colleagues wrote a tribute booklet, and Susan immediately booked a trip to Portugal.',
                'event_date'  => '2015-06-01',
                'category'    => 'Career',
            ],

            // ── TRAVEL (additional) ────────────────────────────────────
            [
                'title'       => 'Grand Canyon Road Trip',
                'description' => 'The family\'s first big road trip. Robert drove 18 hours with Susan navigating by paper map and two-year-old Emma asleep in the back. Standing at the South Rim, Robert said it was worth every mile. Susan said it was worth every stop at every rest area too.',
                'event_date'  => '1987-06-28',
                'category'    => 'Travel',
            ],
            [
                'title'       => 'Yellowstone Family Camping Trip',
                'description' => 'The Johnsons braved tent camping in Yellowstone for a full week. We saw a bison herd, witnessed Old Faithful twice, and Liam stepped in a hot spring puddle on day one. Emma kept a nature journal. The campfire meals tasted better than any restaurant.',
                'event_date'  => '1993-08-05',
                'category'    => 'Travel',
            ],
            [
                'title'       => 'Colorado Ski Trip',
                'description' => 'The family\'s first ski vacation — five days in Breckenridge. Robert had skied once in his twenties and claimed expertise. He spent most of day one on the bunny slope with Liam. Emma took to it instantly and was on blue runs by day two.',
                'event_date'  => '1997-02-20',
                'category'    => 'Travel',
            ],
            [
                'title'       => 'Robert & Susan\'s Alaska Cruise',
                'description' => 'For their 25th anniversary Susan surprised Robert with a 10-day cruise to Alaska. Glacier Bay, Juneau, and Ketchikan. Robert spotted a humpback whale and talked about it for two years. Susan says the seafood alone was worth the trip.',
                'event_date'  => '2007-07-02',
                'category'    => 'Travel',
            ],
            [
                'title'       => 'Liam Backpacks Southeast Asia',
                'description' => 'After finishing his Master\'s, Liam spent eight weeks travelling solo through Thailand, Vietnam, and Cambodia. He came back thinner, tanned, and with 14,000 photos. The street food in Hanoi, he insists, ruined all other noodles for him permanently.',
                'event_date'  => '2013-09-01',
                'category'    => 'Travel',
            ],
            [
                'title'       => 'Emma & Tom\'s Portugal Anniversary Trip',
                'description' => 'To celebrate 10 years of marriage, Emma and Tom spent two weeks in Lisbon, Porto, and the Alentejo. They ate pastel de nata for breakfast every single day and have zero regrets. Susan finally got a postcard.',
                'event_date'  => '2019-08-20',
                'category'    => 'Travel',
            ],
            [
                'title'       => 'Family Ski Weekend at Whistler',
                'description' => 'Three generations hit the slopes at Whistler Blackcomb. Sophia was a natural on skis — she overtook Robert on the first green run. Noah built a snow fort at the base lodge and refused to leave it. Best hot chocolate of anyone\'s life.',
                'event_date'  => '2020-02-14',
                'category'    => 'Travel',
            ],
            [
                'title'       => 'Iceland: The Northern Lights',
                'description' => 'Liam and Kate took the trip of a lifetime to Iceland in February. On night three, the aurora australis lit up the sky in curtains of green and purple. Kate cried. Liam insists he didn\'t. The photos say otherwise.',
                'event_date'  => '2023-02-08',
                'category'    => 'Travel',
            ],

            // ── MOVE (additional) ──────────────────────────────────────
            [
                'title'       => 'Robert & Susan\'s First Apartment',
                'description' => 'A third-floor walkup in Sacramento with one bedroom, no dishwasher, and a radiator that only worked on its own schedule. They loved it. They\'ve never been more broke or more happy. The neighbours downstairs had a cat named Gerald.',
                'event_date'  => '1980-03-01',
                'category'    => 'Move',
            ],
            [
                'title'       => 'Emma Moves to Portland for Work',
                'description' => 'Fresh degree, job offer in hand, Emma loaded a rented van and moved to Portland three weeks after graduation. She cried saying goodbye to Austin and was unpacked in her new apartment within 48 hours. She called home every night that first week.',
                'event_date'  => '2007-09-05',
                'category'    => 'Move',
            ],
            [
                'title'       => 'Emma & Tom Buy Their First Home',
                'description' => 'After two years of searching, Emma and Tom closed on a 1920s craftsman bungalow in NE Portland. Robert insisted on inspecting the foundation. Susan brought a housewarming plant that is still alive today, against all odds.',
                'event_date'  => '2011-04-22',
                'category'    => 'Move',
            ],
            [
                'title'       => 'Liam Buys His First Place in Seattle',
                'description' => 'Liam bought a one-bedroom condo in Capitol Hill — a deliberate starter home with a deliberate espresso machine. He hosted a housewarming dinner for eight people in a space designed for four. Everyone agreed it was the most fun they\'d had in years.',
                'event_date'  => '2012-07-15',
                'category'    => 'Move',
            ],
            [
                'title'       => 'Liam & Kate Move to a Family Home',
                'description' => 'With Noah on the way and a startup growing, Liam and Kate moved from the condo into a proper house in Fremont with a yard. Robert helped paint the nursery. It took two weekends and considerable debate over the exact shade of blue.',
                'event_date'  => '2015-01-10',
                'category'    => 'Move',
            ],
            [
                'title'       => 'Robert & Susan Downsize in Austin',
                'description' => 'With Robert retired and the kids long moved out, the family home felt a size too big. They found a smaller place closer to the city — walkable, manageable, and with a garden Susan immediately started redesigning. Robert cried packing up the kids\' old rooms.',
                'event_date'  => '2017-03-12',
                'category'    => 'Move',
            ],
            [
                'title'       => 'Grandma Eleanor Moves to Hawthorn Grove',
                'description' => 'At 94, Grandma Eleanor made the move to Hawthorn Grove retirement community. She picked her apartment from three options, chose based entirely on which had the best view, and was hosting bridge nights within two weeks.',
                'event_date'  => '2019-05-20',
                'category'    => 'Move',
            ],
            [
                'title'       => 'Emma & Tom Relocate to the Coast',
                'description' => 'After years of talking about it, Emma and Tom made the leap and moved to a house on the Oregon coast. Remote work made it possible; the sound of the ocean every morning made it permanent. Sophia says it\'s the best decision her parents ever made.',
                'event_date'  => '2022-05-01',
                'category'    => 'Move',
            ],

            // ── CAREER (additional) ────────────────────────────────────
            [
                'title'       => 'Robert Joins the Firm',
                'description' => 'Robert\'s first day at Hartley & Associates — a briefcase he bought himself, a suit Susan pressed twice that morning, and a commute he\'d make for the next 35 years. He called it a good firm on day one. He was still saying it on his last day.',
                'event_date'  => '1980-09-08',
                'category'    => 'Career',
            ],
            [
                'title'       => 'Susan Completes Her Baking Diploma',
                'description' => 'After years of baking for family and friends, Susan enrolled in a professional patisserie programme and earned her diploma. Her layered croissant was described by the examiner as "technically flawless." She has the certificate framed in the bakery.',
                'event_date'  => '1995-06-15',
                'category'    => 'Career',
            ],
            [
                'title'       => 'Robert Appointed Vice President',
                'description' => 'After 20 years of steady, loyal work, Robert was appointed Vice President of Operations. The board announcement came on a Tuesday. Susan had champagne on ice by Wednesday morning.',
                'event_date'  => '2000-04-01',
                'category'    => 'Career',
            ],
            [
                'title'       => 'Emma Promoted to Marketing Director',
                'description' => 'Three years into her career at the Portland agency, Emma was promoted to Director. She was 28. She promptly renegotiated her entire team\'s compensation. Her manager called it audacious. Her team called it overdue.',
                'event_date'  => '2010-03-15',
                'category'    => 'Career',
            ],
            [
                'title'       => 'The Bread Basket Wins "Best Local Bakery"',
                'description' => 'Susan\'s bakery was voted Best Local Bakery in the Austin Chronicle\'s annual reader poll — the first of what would become five consecutive wins. Susan said she was "a little surprised." Nobody else was.',
                'event_date'  => '2012-09-20',
                'category'    => 'Career',
            ],
            [
                'title'       => 'Emma Launches Her Own Consultancy',
                'description' => 'After a decade in agencies, Emma went independent. Johnson & Co. launched with three clients on day one and a waiting list by month three. Tom built the website. Emma says it was his finest work. Tom agrees.',
                'event_date'  => '2016-01-04',
                'category'    => 'Career',
            ],
            [
                'title'       => 'Liam\'s Startup Raises Series A',
                'description' => 'After two years of bootstrapping, Liam\'s machine learning startup closed a $4.2M Series A round. The call came on a Sunday afternoon while the family was at a barbecue. Liam went very quiet, then very loud. Kate already knew.',
                'event_date'  => '2016-11-08',
                'category'    => 'Career',
            ],
            [
                'title'       => 'Susan Sells The Bread Basket',
                'description' => 'After 24 years, Susan handed The Bread Basket to a young couple who had worked there for five years. The handover took three months and included a folder of every recipe she\'d ever created. She still visits on Saturday mornings.',
                'event_date'  => '2021-08-01',
                'category'    => 'Career',
            ],

            // ── HEALTH (4) ─────────────────────────────────────────────
            [
                'title'       => "Grandpa Henry's Open-Heart Surgery",
                'description' => 'A scary two weeks in November. Grandpa came through the surgery brilliantly. He was out of hospital in 10 days and asking for his crossword puzzle by day 3.',
                'event_date'  => '1999-11-12',
                'category'    => 'Health',
            ],
            [
                'title'       => 'Emma Runs Her First Marathon',
                'description' => 'The Portland Marathon. Emma trained for eight months and crossed the finish line in 4:12. She swore she\'d never do it again. She has since run three more.',
                'event_date'  => '2011-10-09',
                'category'    => 'Health',
            ],
            [
                'title'       => 'Liam Completes His First Triathlon',
                'description' => 'Swim, bike, run in Coeur d\'Alene. Liam finished 47th in his age group, which he describes as "podium-adjacent". We are very proud.',
                'event_date'  => '2019-07-21',
                'category'    => 'Health',
            ],
            [
                'title'       => 'Family Wellness Challenge',
                'description' => 'The whole extended family joined a 30-day wellness challenge at the start of 2023. Daily step counts were shared in a group chat that got extremely competitive by week two.',
                'event_date'  => '2023-01-01',
                'category'    => 'Health',
            ],

            // ── OTHER (5) ──────────────────────────────────────────────
            [
                'title'       => 'First Johnson Family Reunion',
                'description' => 'Both sides of the family gathered at the lake house for the very first time. 42 people, three generations, one very overwhelmed BBQ grill. A tradition was born.',
                'event_date'  => '1990-08-10',
                'category'    => 'Other',
            ],
            [
                'title'       => 'We Adopted Max (the Dog)',
                'description' => 'Emma and Liam had been begging for a dog for years. A golden retriever mix from the local shelter finally convinced Robert. Max was the best Christmas present ever given.',
                'event_date'  => '2002-12-25',
                'category'    => 'Other',
            ],
            [
                'title'       => 'The Johnson Family Newsletter — Vol. 1',
                'description' => 'Susan started the family newsletter in 2015. Monthly highlights, recipes, milestone roundups, and one recurring column by Robert called "Things I Have Opinions About".',
                'event_date'  => '2015-02-01',
                'category'    => 'Other',
            ],
            [
                'title'       => 'Staying Connected During the Pandemic',
                'description' => 'When the world went quiet in 2020, the Johnsons went digital. Weekly Zoom dinners, a family recipe swap, virtual game nights, and Susan\'s famous sourdough starter being shared by mail.',
                'event_date'  => '2020-04-15',
                'category'    => 'Other',
            ],
            [
                'title'       => 'Johnson Family Time Capsule',
                'description' => 'On New Year\'s Day, each family member contributed a letter, a photo, and a small object to a time capsule buried in Grandma\'s garden. To be opened in 2044.',
                'event_date'  => '2024-01-01',
                'category'    => 'Other',
            ],
        ];

        foreach ($events as $data) {
            $categoryId = $cat($data['category']);

            $event = Event::firstOrCreate(
                [
                    'group_id' => $group->id,
                    'title'    => $data['title'],
                ],
                [
                    'description'            => $data['description'],
                    'event_date'             => $data['event_date'],
                    'category_id'            => $categoryId,
                    'created_by'             => $admin->id,
                    'visibility'             => 'public',
                    'social_visibility'      => 'public',
                    'visibility_is_override' => true,
                ]
            );

            // Apply demo image if one exists for this event
            if (isset($imageMap[$data['title']])) {
                $event->update(['image_url' => $imageMap[$data['title']]]);
            }
        }

        $this->command->info('Demo seeded: "The Johnson Family" group with ' . count($events) . ' events.');
    }
}
