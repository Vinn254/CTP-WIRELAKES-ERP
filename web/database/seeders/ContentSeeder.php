<?php

namespace Database\Seeders;

use App\Models\ContentEntry;
use App\Models\ContentField;
use App\Models\ContentSection;
use App\Models\ContentValue;
use Illuminate\Database\Seeder;

class ContentSeeder extends Seeder
{
    public function run(): void
    {
        // ---------- site (global branding / footer) ----------
        $this->put('site', 'branding', 'Branding & logo', 'single',
            ['logo' => ['Site logo', 'image'], 'brand_name' => ['Brand name', 'text'],
             'theme_green' => ['Brand colour (dark)', 'text'], 'theme_lime' => ['Accent colour (lime)', 'text']],
            [['brand_name' => 'WireLakes Technologies', 'theme_green' => '#066722', 'theme_lime' => '#4cb614']]);

        $this->put('site', 'backgrounds', 'Background photos', 'single',
            ['hero_photo' => ['Hero background photo', 'image'],
             'page_photo' => ['Whole-site background photo', 'image'],
             'photo_darkness' => ['Photo darkness over hero (0.3 – 0.85)', 'text']],
            [['photo_darkness' => '0.66']]);

        $this->put('site', 'contact', 'Contact details', 'single',
            ['email' => ['Email', 'text'], 'phone' => ['Phone', 'text'], 'address' => ['Address', 'textarea']],
            [['email' => 'info@ctp-wirelakes.ke', 'phone' => '+254 700 000 000', 'address' => "WireLakes Technologies\nKisumu, Kenya"]]);

        $this->put('site', 'footer', 'Footer', 'single',
            ['about_text' => ['About blurb', 'textarea'], 'fine_print' => ['Copyright line', 'text']],
            [['about_text' => 'Community Technology Promoters — E-Fundis, Training & Innovation. From a community need, we create trained people, certified fundis, completed projects and lasting jobs.',
              'fine_print' => '© '.date('Y').' WireLakes Technologies · Designed by James Ombet & Vincent Otieno, Kisumu, Kenya']]);

        // ---------- home ----------
        $this->put('home', 'hero', 'Hero (top banner)', 'single',
            ['kicker' => ['Kicker badge', 'text'], 'title' => ['Headline', 'textarea'], 'lede' => ['Intro paragraph', 'textarea'],
             'cta1_label' => ['Button 1 label', 'text'], 'cta1_url' => ['Button 1 link', 'text'],
             'cta2_label' => ['Button 2 label', 'text'], 'cta2_url' => ['Button 2 link', 'text']],
            [['kicker' => 'Community Technology Promoters · Kenya',
              'title' => 'We train and certify people — then give the projects to our Fundis.',
              'lede' => 'WireLakes Technologies connects Kenyan communities with technical training, skilled E-Fundis, employment, enterprise development and green-economy projects — across all 47 counties.',
              'cta1_label' => 'Become a student (CTP)', 'cta1_url' => '/register/ctp',
              'cta2_label' => 'Become a Fundi', 'cta2_url' => '/register/fundi']]);

        $this->put('home', 'hero_slides', 'Hero sliding images', 'repeater',
            ['image' => ['Photo', 'image'], 'caption' => ['Caption', 'text']],
            [['caption' => 'Training'], ['caption' => 'E-Fundis'], ['caption' => 'Projects']]);

        $this->put('home', 'programme', 'What the programme does — heading', 'single',
            ['title' => ['Title', 'text'], 'sub' => ['Subtitle', 'text'], 'cta_label' => ['Button label', 'text'], 'cta_url' => ['Button link', 'text']],
            [['title' => 'What the programme does', 'sub' => 'Six connected areas of work, run as one ecosystem.', 'cta_label' => 'See all six areas of work', 'cta_url' => '/what-we-do']]);

        $this->put('home', 'programme_cards', 'Programme cards', 'repeater',
            ['icon' => ['Icon (emoji)', 'text'], 'title' => ['Title', 'text'], 'desc' => ['Description', 'textarea']],
            [
                ['icon' => '🎓', 'title' => 'Training', 'desc' => 'Community-based technical training with certification recognised across the industry.'],
                ['icon' => '🛠️', 'title' => 'E-Fundi Marketplace', 'desc' => 'Verified fundis — intern, junior and senior tiers — matched to real projects.'],
                ['icon' => '🏗️', 'title' => 'Projects', 'desc' => 'Schools, businesses and communities post projects; the right team is assigned.'],
                ['icon' => '♻️', 'title' => 'E-Waste', 'desc' => 'Collection, repair, refurbishment and safe disposal — turning waste into work.'],
                ['icon' => '💼', 'title' => 'Entrepreneurship', 'desc' => 'Business support for fundis and CTPs so skills become enterprises.'],
                ['icon' => '🌱', 'title' => 'Green Economy', 'desc' => 'Solar, green tech and community technology serving a sustainable Kenya.'],
            ]);

        $this->put('home', 'donate_strip', 'Donate strip', 'single',
            ['title' => ['Title', 'text'], 'text' => ['Text', 'text'], 'cta_label' => ['Button label', 'text']],
            [['title' => 'Every donation goes directly to programme work', 'text' => 'Training fundis, certifying skills and equipping communities across Kenya.', 'cta_label' => 'Donate now']]);

        $this->put('home', 'partners_heading', 'Partners heading (home)', 'single',
            ['title' => ['Title', 'text']],
            [['title' => 'Our partners']]);

        // ---------- about ----------
        $this->put('about', 'intro', 'Origin story', 'single',
            ['tag' => ['Tag', 'text'], 'title' => ['Title', 'text'], 'body1' => ['Paragraph 1', 'textarea'], 'body2' => ['Paragraph 2', 'textarea']],
            [['tag' => 'About the programme', 'title' => 'Our origin',
              'body1' => 'WireLakes Technologies started in Kisumu, Kenya, with a simple idea: every community has needs — and the same community has people who can be trained, certified and deployed to meet those needs. What began as a skills initiative for school leavers and junior graduates has grown into a multi-county programme spanning training, the E-Fundi workforce, projects, construction, technology services, e-waste and the green economy.',
              'body2' => 'The programme is built around the CTP — the Community Technology Promoter — an on-the-ground agent who is simultaneously a trainee, a mobilizer and a community agent.']]);

        $this->put('about', 'pillars', 'Vision / Mission / Values cards', 'repeater',
            ['title' => ['Title', 'text'], 'desc' => ['Text', 'textarea']],
            [
                ['title' => '🎯 Vision', 'desc' => 'From a community need, the CTP system creates trained people, certified fundis, completed projects, refurbished e-waste, new businesses and lasting jobs — then loops the impact back into the community.'],
                ['title' => '🚀 Mission', 'desc' => 'To connect people with technical training, skilled technicians (E-Fundis), employment opportunities, enterprise development, digital services, e-waste management and community technology projects at scale across Kenya.'],
                ['title' => '💎 Values', 'desc' => 'Community first · Skills with proof · Integrity in every certification · Green by design · Jobs that stay local · Transparency to donors and partners.'],
            ]);

        $this->put('about', 'operate', 'Where we operate', 'single',
            ['title' => ['Title', 'text'], 'sub' => ['Subtitle', 'textarea'], 'card_icon' => ['Card icon (emoji)', 'text'], 'card_title' => ['Card title', 'text'], 'card_text' => ['Card text', 'textarea']],
            [['title' => 'Where we operate',
              'sub' => 'WireLakes Technologies works county-by-county across Kenya, starting from the Lake Victoria region and expanding through CTP mobilization.',
              'card_icon' => '🗺️', 'card_title' => 'Kisumu · Kenya',
              'card_text' => 'Head office and programme birthplace. CTPs active across wards, with county expansion driven by Modules 2 and 3.']]);

        // ---------- what we do ----------
        $this->put('what-we-do', 'intro', 'Page heading', 'single',
            ['tag' => ['Tag', 'text'], 'title' => ['Title', 'text'], 'sub' => ['Subtitle', 'textarea']],
            [['tag' => 'What we do', 'title' => 'Six areas of work, one connected ecosystem',
              'sub' => 'Train and certify → deploy to projects → build and maintain → recycle → start businesses → employ the community. Then the loop repeats.']]);

        $this->put('what-we-do', 'cards', 'Work-area cards', 'repeater',
            ['icon' => ['Icon (emoji)', 'text'], 'title' => ['Title', 'text'], 'desc' => ['Description', 'textarea'], 'cta_label' => ['Link label', 'text'], 'cta_url' => ['Link URL', 'text']],
            [
                ['icon' => '🎓', 'title' => 'Training & Certification', 'desc' => 'Courses and cohorts at community sites and partner schools — theory, practical work, assessment and a certificate with a verifiable ID.', 'cta_label' => 'Join as a CTP trainee', 'cta_url' => '/register/ctp'],
                ['icon' => '🛠️', 'title' => 'E-Fundi Marketplace', 'desc' => 'Verified skilled workers — electricians, masons, ICT and solar technicians — matched to real projects by specialisation and county.', 'cta_label' => 'Register as a Fundi', 'cta_url' => '/register/fundi'],
                ['icon' => '🏗️', 'title' => 'Projects & Construction', 'desc' => 'We post projects from our trusted partners — from computer labs to roofs and fences. We assess, quote, assign, execute and sign off.', 'cta_label' => 'View open projects', 'cta_url' => '/projects'],
                ['icon' => '♻️', 'title' => 'E-Waste Management', 'desc' => 'Collection, weighing, sorting, testing — then repair, refurbish, recycle or dispose safely. Refurbished gear returns to schools and communities.', 'cta_label' => 'Partner with us', 'cta_url' => '/contact'],
                ['icon' => '💼', 'title' => 'Entrepreneurship', 'desc' => 'Fundis and CTPs get business support, toolkits and first contracts so a skill becomes an enterprise that hires locally.', 'cta_label' => 'Fund a toolkit', 'cta_url' => '/donate'],
                ['icon' => '🌱', 'title' => 'Green Economy & Technology', 'desc' => 'Solar, ICT, networking, robotics, programming and AI services delivered by our own trained workforce.', 'cta_label' => 'Learn more', 'cta_url' => '/about'],
            ]);

        // ---------- the six public area pages ----------
        // Headings and offers are the programme's own words. Pictures, clips and testimonials are left empty on
        // purpose: the office uploads what it has actually shot and types what people actually said.
        $this->areas();

        // ---------- faq ----------
        $this->put('faq', 'intro', 'Page heading', 'single',
            ['tag' => ['Tag', 'text'], 'title' => ['Title', 'text']],
            [['tag' => 'FAQ', 'title' => 'Frequently asked questions']]);

        $this->put('faq', 'items', 'Questions & answers', 'repeater',
            ['question' => ['Question', 'text'], 'answer' => ['Answer', 'textarea']],
            [
                ['question' => 'What is a CTP?', 'answer' => 'A Community Technology Promoter — the on-the-ground agent who is simultaneously a trainee (learning a skill), a mobilizer (bringing people into the programme) and a community agent (identifying needs and registering communities).'],
                ['question' => 'Who can become a CTP?', 'answer' => 'School leavers (training track) and junior graduates wanting practical experience (junior practical-check track).'],
                ['question' => 'What is the difference between a Fundi-Intern, an Attachment applicant, a Junior Fundi and a Senior Fundi?', 'answer' => 'Fundi-Intern: graduated, no experience, seeking an internship. Attachment applicant: still in school and not yet graduated — only they can apply for industrial attachment. Junior Fundi: recent graduate who already has internship experience. Senior Fundi: long industry experience, fully eligible to lead projects and mentor.'],
                ['question' => 'Do I have to be a CTP first to become a Fundi?', 'answer' => 'No. Fundi registration is a standalone path. Skilled workers apply directly with their certificates and a referee.'],
                ['question' => 'What documents do I need to register?', 'answer' => 'CTP: national ID scan, passport photo, and any school certificate. Fundi: ID, business certificate (if any), trade certificates and up to 6 portfolio photos. Hirer: an ID or organisation document.'],
                ['question' => 'Is registering as a hirer free?', 'answer' => 'Yes — hirer registration is free, but every organisation is verified by an admin to prevent fraud.'],
                ['question' => 'How do I pay what the programme asks for?', 'answer' => 'For any type of payment use the Paybill and account number the office gives you, and put your reference or national ID number as the reason so the payment is matched to your name. No application waits on a payment — it goes straight to review.'],
                ['question' => 'How long does approval take?', 'answer' => 'Directors and admins review applications with all documents. You will be notified by email and SMS once a decision is made.'],
            ]);

        // ---------- donate ----------
        $this->put('donate', 'intro', 'Page heading', 'single',
            ['tag' => ['Tag', 'text'], 'title' => ['Title', 'text'], 'sub' => ['Subtitle', 'textarea']],
            [['tag' => 'Donate', 'title' => 'Fund a skill. Change a life.',
              'sub' => 'Every donation goes directly to programme work. One-time or recurring — M-Pesa, card or bank.']]);

        // ---------- partners ----------
        $this->put('partners', 'intro', 'Page heading', 'single',
            ['tag' => ['Tag', 'text'], 'title' => ['Title', 'text']],
            [['tag' => 'Partners', 'title' => 'Organisations that power the programme']]);

        $this->put('partners', 'empty_state', 'Empty partner wall message', 'single',
            ['icon' => ['Icon (emoji)', 'text'], 'title' => ['Title', 'text'], 'text' => ['Text', 'textarea']],
            [['icon' => '🤝', 'title' => 'The partner wall is being filled', 'text' => 'Our first partner logos will appear here. Want to be one of them? Use the form below.']]);

        $this->put('partners', 'form', 'Partner form heading', 'single',
            ['title' => ['Title', 'text'], 'sub' => ['Subtitle', 'textarea']],
            [['title' => 'Become a Partner', 'sub' => 'Tiers: Platinum · Gold · Silver. Listed partners appear on the logo wall and home page.']]);

        // ---------- contact ----------
        $this->put('contact', 'intro', 'Page heading', 'single',
            ['tag' => ['Tag', 'text'], 'title' => ['Title', 'text']],
            [['tag' => 'Contact', 'title' => 'Talk to us']]);

        $this->put('contact', 'office', 'Office card', 'single',
            ['card_title' => ['Card title', 'text'], 'address' => ['Address lines', 'textarea'], 'phone' => ['Phone', 'text'], 'email' => ['Email', 'text'], 'faq_note' => ['FAQ link note', 'text']],
            [['card_title' => 'Kisumu head office', 'address' => "WireLakes Technologies\nKisumu, Kenya", 'phone' => '+254 700 000 000', 'email' => 'info@ctp-wirelakes.ke', 'faq_note' => 'Read the FAQ first? Many answers are there →']]);

        $this->put('contact', 'form', 'Message form heading', 'single',
            ['title' => ['Title', 'text']],
            [['title' => 'Send a message']]);

        // ---------- membership settings ----------
        $this->put('membership', 'fees', 'Membership fees', 'single',
            ['fee_ctp' => ['CTP fee (KSh)', 'price'], 'fee_fundi' => ['Fundi fee (KSh)', 'price'], 'fee_hirer' => ['Hirer fee (KSh — 0 = free)', 'price'], 'payment_note' => ['Payment note on success page', 'textarea']],
            [['fee_ctp' => '1000', 'fee_fundi' => '1000', 'fee_hirer' => '0',
              'payment_note' => 'For any type of payment use the Paybill and account number the office gives you, and put your reference or national ID number as the reason. No application waits on a payment — it goes straight to review.']]);

        $this->put('membership', 'ctp_tracks', 'CTP training tracks', 'repeater',
            ['code' => ['Code (do not change once live)', 'text'], 'label' => ['Label', 'text'], 'desc' => ['Help text', 'textarea']],
            [
                ['code' => 'school_leaver', 'label' => 'School-leaver training track', 'desc' => 'You have not yet learned a trade — the programme trains and certifies you from zero.'],
                ['code' => 'junior', 'label' => 'Junior practical-check track', 'desc' => 'You already hold a basic certificate and want practical experience or a job.'],
            ]);

        $this->put('membership', 'fundi_tiers', 'E-Fundi tiers', 'repeater',
            ['code' => ['Code (do not change once live)', 'text'], 'label' => ['Tier name', 'text'], 'desc' => ['Who it is for', 'textarea']],
            [
                ['code' => 'fundi_intern', 'label' => 'Fundi-Intern', 'desc' => 'Graduated, no experience, seeking an internship'],
                ['code' => 'attachment', 'label' => 'Attachment applicant', 'desc' => 'Still in school, not yet graduated'],
                ['code' => 'junior_fundi', 'label' => 'Junior Fundi', 'desc' => 'Recent graduate with internship experience'],
                ['code' => 'senior_fundi', 'label' => 'Senior Fundi', 'desc' => 'Long industry experience'],
            ]);
    }

    /**
     * Sections every area page needs: a heading block, the work the area explains line by line with a picture beside
     * each explanation, a picture-and-clip gallery and the testimonials band. The gallery and testimonial rows start
     * empty for the office to fill with its own shots and the real words of the people served.
     */
    protected function areas(): void
    {
        $offers = [
            'training' => [
                ['title' => 'Cohorts at community sites', 'desc' => "A training does not wait for people to come to a town centre — we run it as a cohort at a community venue or a partner school, with one trainer assigned to that site.\nEvery cohort has a timetable, an attendance register kept day by day, and a practical check at each site. You are taught where you live, and the record of your attendance is the office's, not a piece of paper you must keep safe."],
                ['title' => 'Modules and a track', 'desc' => "Each training is divided into modules, and the modules are put together into a track. A trainer marks you module by module — attendance, the practical, the written work — and the track shows the percentage reached.\nYou see your own progress on your screen the whole way through. Nothing is counted that the trainer has not recorded and the Registrar has not approved."],
                ['title' => 'Certification', 'desc' => "When a track reaches 100% the trainee goes into the certification queue, where the office checks the record and signs it off. What comes out is a record the programme can verify when an employer or a hirer asks.\nA certificate is issued by the executive desk. Staff prepare the file; the person who signs is named on it."],
                ['title' => 'Trainers', 'desc' => "The people who teach are our own approved fundis and CTPs who finished a track and were then assessed to pass it on. They hand in their register, marks, timetable and lesson plans for the Registrar to approve.\nIf you want to teach with us, you register as a Fundi first — a trainer is a graded fundi the training desk has assigned to a site."],
            ],
            'marketplace' => [
                ['title' => 'Electrical installation', 'desc' => "Wiring, boards, connections and testing for a home, a school or a business, done by verified E-Fundis under the marketplace desk. You tell us the building and the work; we send a graded fundi and the desk stands behind the job.\nEvery installation is handed over with a record of who did it, so a later fault has a name and a number to call."],
                ['title' => 'Solar installation', 'desc' => "Panels, batteries, inverters and the cabling between them, fitted and tested by our own trained workforce rather than a sub-contracter we have never met.\nA system is sized to what you actually run — lighting, sockets, a pump, a fridge — and the people who use it are shown how to run it after we leave."],
                ['title' => 'Training for your group', 'desc' => "A course delivered at your site for your own people — a village group, a SACCO, a school's staff — with assessment at the end and a record of every person who sat it.\nChoose the training from the catalogue the office keeps; tell us the number of people and where, and the desk quotes for it."],
                ['title' => 'A fundi for a job', 'desc' => "Tell us the work and the county. The marketplace desk searches its own register of graded fundis and puts the right people forward, with their grade and the work they have already completed.\nYou are not sent a stranger from an advert — you are sent someone the programme has trained and can account for."],
                ['title' => 'A trainer', 'desc' => "An approved trainer for a cohort, a workshop or a practical check, released by the training desk and recorded against your project so the paperwork that follows has an owner."],
                ['title' => 'Web design', 'desc' => "A site for a school, a SACCO, a chama or a small business — built, then kept updated by the technology desk so it does not go dead after launch week.\nThe site states who you are and what you offer, and it carries the same care as our own."],
                ['title' => 'Software design', 'desc' => "Record systems and tools that replace a paper book: a register, a rota, a book of accounts, a queue of requests. Built by our own developers on the same system the programme runs itself on."],
                ['title' => 'Cyber services', 'desc' => "Printing, scanning, photocopying, CV typing and online applications — HELB, KUCCPS, NHIF, eCitizen and a tender pack, done with you at the desk rather than sent off alone.\nThis is the counter people walk into first, and the same staff who handle the paperwork can set up the site or the system behind it."],
            ],
            'e-waste' => [
                ['title' => 'Collection', 'desc' => "We collect e-waste from a home, an office or a school. An E-Fundi registered on this desk picks it up, and the load is weighed in at the depot so the amount handled is a figure, not a guess.\nYou get a record of what left your premises and where it went."],
                ['title' => 'Testing and sorting', 'desc' => "Every item is tested before it is judged. What still has life in it goes for repair, what can be built up again goes for refurbishment, and what is finished goes to safe disposal.\nSorting is what keeps a working machine out of a hole."],
                ['title' => 'Repair and refurbishment', 'desc' => "Our waste innovators bring equipment back to working order — screens, boards, power supplies, small appliances. A repaired item is tested, then offered to a school or a community centre that needs it.\nThis is where a trained fundi earns twice: the waste is cleared and a classroom gets equipment."],
                ['title' => 'Safe disposal', 'desc' => "What cannot be saved is disposed of the way the rules require, with the disposal recorded against the collection that brought it in. Nothing is burnt and nothing is buried with the general rubbish."],
                ['title' => 'Refurbished gear for schools', 'desc' => "Worked-on equipment goes back into classrooms and community centres, where it is used rather than stored. A school that needs a lab tells this desk and the gear is allocated from the stock already refurbished."],
            ],
            'entrepreneurship' => [
                ['title' => 'Business support', 'desc' => "A fundi or a CTP with a skill and no order is the person this area exists for. They get the support to register, price their work and keep books, and the marketplace desk gives them the first contract.\nThe training is only finished when the skill is earning."],
                ['title' => 'Toolkits', 'desc' => "Tools and equipment that make an enterprise able to take work the day it starts — a tool set, a ladder, machine, meters or a kit for the specific trade. A toolkit is asked for against a plan, not handed out at random."],
                ['title' => 'Enterprise that hires locally', 'desc' => "A business started here employs people from the same community the training came from. The credit a CTP earns is measured by the people they brought in, and the jobs a new enterprise creates stay in that county."],
            ],
            'green-economy' => [
                ['title' => 'Solar installation', 'desc' => "Solar for a home, a school or a clinic — fitted, tested and handed over with training for the people who will run it. A school that has never had power gets the system and the fundi who maintains it.\nThe work is carried out by the same workforce the training area produces, which is why a fault is answered by someone we know."],
                ['title' => 'Green technology', 'desc' => "Energy-efficient fittings and community technology that lower a running cost rather than only looking modern on a brochure — water pumping, efficient lighting, backup power for a facility that cannot afford to go dark."],
                ['title' => 'ICT, networking and robotics', 'desc' => "Networking, robotics, programming and AI services delivered by our own trained workforce, installed and then supported under a warranty the technology desk stands behind. A lab that stops working is a ticket on that desk, not a phone call into the wind."],
            ],
        ];

        $headings = [
            'training' => ['Training', 'Training & Certification',
                'Courses and cohorts at community sites and partner schools — theory, practical work, assessment and a certificate.'],
            'marketplace' => ['Marketplace', 'The Marketplace',
                'This is where you ask us to do the work. We offer electrical and solar installation, training for a group, a fundi for a job, a trainer, web design, software design and cyber services.'],
            'e-waste' => ['E-Waste', 'E-Waste Management',
                'The e-waste we have collected, repaired and given back to schools — with the pictures of the work. Ask us for a collection, or register to collect and repair yourself.'],
            'entrepreneurship' => ['Entrepreneurship', 'Entrepreneurship',
                'What the programme offers a fundi or a CTP turning a skill into a business, and what has been achieved so far.'],
            'green-economy' => ['Green Economy', 'Green Economy & Technology',
                'Solar, green technology and community technology, installed and maintained by our own workforce — the projects and the pictures of the work.'],
        ];

        foreach ($headings as $slug => [$tag, $title, $sub]) {
            $this->put($slug, 'intro', 'Page heading', 'single',
                ['tag' => ['Tag', 'text'], 'title' => ['Title', 'text'], 'sub' => ['Subtitle', 'textarea'],
                 'work_title' => ['Work heading', 'text'], 'work_note' => ['Work note', 'textarea'],
                 'gallery_title' => ['Pictures heading', 'text'], 'voices_title' => ['Testimonials heading', 'text'],
                 'apply_title' => ['Request heading', 'text'], 'apply_note' => ['Request note', 'textarea'],
                 'join_title' => ['Join heading', 'text'], 'join_note' => ['Join note', 'textarea']],
                [['tag' => $tag, 'title' => $title, 'sub' => $sub,
                  'work_title' => 'What this area actually does',
                  'work_note' => 'Each line below is work this area carries out — what happens, start to finish, with a picture of it beside the words.',
                  'gallery_title' => 'Pictures and clips from the work',
                  'voices_title' => 'From the people we have trained and served',
                  'apply_title' => 'Ask us to do this for you',
                  'apply_note' => 'Tell us what you need done and where you are. The office reads it and contacts you on the number or email you give.',
                  'join_title' => 'Want to join us or work with us instead?',
                  'join_note' => 'Those do not go through the form above — you register so we can verify you, and a job application goes to the Careers desk.']]);

            $this->put($slug, 'offers', 'What we do here', 'repeater',
                ['title' => ['Title', 'text'], 'desc' => ['Explanation', 'textarea'],
                 'photo' => ['Picture of the work', 'image']],
                $offers[$slug]);

            $this->put($slug, 'gallery', 'Pictures & short videos', 'repeater',
                ['photo' => ['Photo', 'image'], 'video' => ['Video clip', 'video'], 'caption' => ['Caption', 'textarea']],
                []);

            $this->put($slug, 'testimonials', 'Testimonials', 'repeater',
                ['quote' => ['In their words', 'textarea'], 'name' => ['Name', 'text'],
                 'role' => ['What they are', 'text'], 'town' => ['Town / county', 'text']],
                []);
        }
    }

    /**
     * Idempotently create a section + its field schema + entry values (only if the section is new).
     */
    protected function put(string $page, string $key, string $name, string $kind, array $fields, array $rows): void
    {
        $existing = ContentSection::where(['page' => $page, 'key' => $key])->first();
        if ($existing && $existing->fields()->exists()) {
            return; // already seeded — never overwrite the admin's edits
        }

        $section = $existing ?: ContentSection::create(['page' => $page, 'key' => $key, 'name' => $name, 'kind' => $kind, 'sort_order' => 0]);

        $fieldModels = [];
        $order = 0;
        foreach ($fields as $fkey => [$label, $type]) {
            $fieldModels[$fkey] = ContentField::create([
                'section_id' => $section->id, 'key' => $fkey, 'label' => $label, 'type' => $type, 'sort_order' => $order++,
            ]);
        }

        foreach ($rows as $i => $row) {
            $entry = ContentEntry::create(['section_id' => $section->id, 'sort_order' => $i]);
            foreach ($row as $fkey => $value) {
                if (! isset($fieldModels[$fkey])) continue;
                $def = $fieldModels[$fkey];
                $isMedia = in_array($def->type, ['image', 'video'], true);

                ContentValue::create([
                    'entry_id' => $entry->id,
                    'field_id' => $def->id,
                    $isMedia ? 'image_path' : 'text_value' => $value,
                ]);
            }
        }
    }
}
