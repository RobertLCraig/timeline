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

            Event::firstOrCreate(
                [
                    'group_id' => $group->id,
                    'title'    => $data['title'],
                ],
                [
                    'description'          => $data['description'],
                    'event_date'           => $data['event_date'],
                    'category_id'          => $categoryId,
                    'created_by'           => $admin->id,
                    'visibility'           => 'public',
                    'social_visibility'    => 'public',
                    'visibility_is_override' => true,
                ]
            );
        }

        $this->command->info('Demo seeded: "The Johnson Family" group with ' . count($events) . ' events.');
    }
}
