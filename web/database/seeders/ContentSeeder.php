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
            [['brand_name' => 'CTP-WireLakes', 'theme_green' => '#066722', 'theme_lime' => '#4cb614']]);

        $this->put('site', 'backgrounds', 'Background photos', 'single',
            ['hero_photo' => ['Hero background photo', 'image'],
             'page_photo' => ['Whole-site background photo', 'image'],
             'photo_darkness' => ['Photo darkness over hero (0.3 – 0.85)', 'text']],
            [['photo_darkness' => '0.66']]);

        $this->put('site', 'contact', 'Contact details', 'single',
            ['email' => ['Email', 'text'], 'phone' => ['Phone', 'text'], 'address' => ['Address', 'textarea']],
            [['email' => 'info@ctp-wirelakes.ke', 'phone' => '+254 700 000 000', 'address' => "CTP-WireLakes Programme\nKisumu, Kenya"]]);

        $this->put('site', 'footer', 'Footer', 'single',
            ['about_text' => ['About blurb', 'textarea'], 'fine_print' => ['Copyright line', 'text']],
            [['about_text' => 'Community Technology Promoters — E-Fundis, Training & Innovation. From a community need, we create trained people, certified fundis, completed projects and lasting jobs.',
              'fine_print' => '© '.date('Y').' CTP-WireLakes Programme · Designed by James Ombet & Vincent Otieno, Kisumu, Kenya']]);

        // ---------- home ----------
        $this->put('home', 'hero', 'Hero (top banner)', 'single',
            ['kicker' => ['Kicker badge', 'text'], 'title' => ['Headline', 'textarea'], 'lede' => ['Intro paragraph', 'textarea'],
             'cta1_label' => ['Button 1 label', 'text'], 'cta1_url' => ['Button 1 link', 'text'],
             'cta2_label' => ['Button 2 label', 'text'], 'cta2_url' => ['Button 2 link', 'text']],
            [['kicker' => 'Community Technology Promoters · Kenya',
              'title' => 'We train and certify people — then give the projects to our Fundis.',
              'lede' => 'CTP-WireLakes connects Kenyan communities with technical training, skilled E-Fundis, employment, enterprise development and green-economy projects — across all 47 counties.',
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
              'body1' => 'CTP-WireLakes (Community Technology Promoters) started in Kisumu, Kenya, with a simple idea: every community has needs — and the same community has people who can be trained, certified and deployed to meet those needs. What began as a skills initiative for school leavers and junior graduates has grown into a multi-county programme spanning training, the E-Fundi workforce, projects, construction, technology services, e-waste and the green economy.',
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
              'sub' => 'CTP-WireLakes works county-by-county across Kenya, starting from the Lake Victoria region and expanding through CTP mobilization. The interactive Kenya-wide impact map arrives with the M&E / GIS department (Module 12).',
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
                ['icon' => '🏗️', 'title' => 'Projects & Construction', 'desc' => 'Hirers post projects — from computer labs to roofs and fences. We assess, quote, assign, execute and sign off.', 'cta_label' => 'Post a Project', 'cta_url' => '/register/hirer'],
                ['icon' => '♻️', 'title' => 'E-Waste Management', 'desc' => 'Collection, weighing, sorting, testing — then repair, refurbish, recycle or dispose safely. Refurbished gear returns to schools and communities.', 'cta_label' => 'Partner with us', 'cta_url' => '/contact'],
                ['icon' => '💼', 'title' => 'Entrepreneurship', 'desc' => 'Fundis and CTPs get business support, toolkits and first contracts so a skill becomes an enterprise that hires locally.', 'cta_label' => 'Fund a toolkit', 'cta_url' => '/donate'],
                ['icon' => '🌱', 'title' => 'Green Economy & Technology', 'desc' => 'Solar, ICT, networking, robotics, programming and AI services delivered by our own trained workforce.', 'cta_label' => 'Learn more', 'cta_url' => '/about'],
            ]);

        // ---------- faq ----------
        $this->put('faq', 'intro', 'Page heading', 'single',
            ['tag' => ['Tag', 'text'], 'title' => ['Title', 'text']],
            [['tag' => 'FAQ', 'title' => 'Frequently asked questions']]);

        $this->put('faq', 'items', 'Questions & answers', 'repeater',
            ['question' => ['Question', 'text'], 'answer' => ['Answer', 'textarea']],
            [
                ['question' => 'What is a CTP?', 'answer' => 'A Community Technology Promoter — the on-the-ground agent who is simultaneously a trainee (learning a skill), a mobilizer (bringing people into the programme) and a community agent (identifying needs and registering communities).'],
                ['question' => 'Who can become a CTP?', 'answer' => 'School leavers (training track) and junior graduates wanting practical experience (junior practical-check track). There is a one-time membership fee set by the programme.'],
                ['question' => 'What is the difference between a Fundi-Intern, an Attachment applicant, a Junior Fundi and a Senior Fundi?', 'answer' => 'Fundi-Intern: graduated, no experience, seeking an internship. Attachment applicant: still in school and not yet graduated — only they can apply for industrial attachment. Junior Fundi: recent graduate who already has internship experience. Senior Fundi: long industry experience, fully eligible to lead projects and mentor.'],
                ['question' => 'Do I have to be a CTP first to become a Fundi?', 'answer' => 'No. Fundi registration is a standalone path. Skilled workers apply directly with their certificates and a referee.'],
                ['question' => 'What documents do I need to register?', 'answer' => 'CTP: national ID scan, passport photo, and any school certificate. Fundi: ID, business certificate (if any), trade certificates and up to 6 portfolio photos. Hirer: an ID or organisation document.'],
                ['question' => 'Is registering as a hirer free?', 'answer' => 'Yes — hirer registration is free, but every organisation is verified by an admin to prevent fraud.'],
                ['question' => 'How is the membership fee paid?', 'answer' => 'Via M-Pesa STK Push or bank transfer. The payment module is being switched on in the next phase; your application simply waits at "pending payment" until then.'],
                ['question' => 'How long does approval take?', 'answer' => 'Directors and admins review applications with all documents. You will be notified by email and SMS once a decision is made.'],
            ]);

        // ---------- donate ----------
        $this->put('donate', 'intro', 'Page heading', 'single',
            ['tag' => ['Tag', 'text'], 'title' => ['Title', 'text'], 'sub' => ['Subtitle', 'textarea'], 'help' => ['Payment note (below form copy)', 'textarea']],
            [['tag' => 'Donate', 'title' => 'Fund a skill. Change a life.',
              'sub' => 'Every donation goes directly to programme work. One-time or recurring — M-Pesa, card or bank.',
              'help' => 'Secure payment (M-Pesa STK Push, card, bank) is being switched on in the next phase. Your pledge is recorded and our team will contact you to complete it.']]);

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
            [['card_title' => 'Kisumu head office', 'address' => "CTP-WireLakes Programme\nKisumu, Kenya", 'phone' => '+254 700 000 000', 'email' => 'info@ctp-wirelakes.ke', 'faq_note' => 'Read the FAQ first? Many answers are there →']]);

        $this->put('contact', 'form', 'Message form heading', 'single',
            ['title' => ['Title', 'text']],
            [['title' => 'Send a message']]);

        // ---------- membership settings ----------
        $this->put('membership', 'fees', 'Membership fees', 'single',
            ['fee_ctp' => ['CTP fee (KSh)', 'price'], 'fee_fundi' => ['Fundi fee (KSh)', 'price'], 'fee_hirer' => ['Hirer fee (KSh — 0 = free)', 'price'], 'payment_note' => ['Payment note on success page', 'textarea']],
            [['fee_ctp' => '1000', 'fee_fundi' => '1000', 'fee_hirer' => '0',
              'payment_note' => 'Membership fees are payable by M-Pesa or bank transfer. Online payment is being switched on in the next phase — our team will contact you with instructions.']]);

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
                ContentValue::create(['entry_id' => $entry->id, 'field_id' => $fieldModels[$fkey]->id, 'text_value' => $value]);
            }
        }
    }
}
