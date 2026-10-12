import docx
from docx.shared import Pt

PATH = r'C:\Users\ADMIN\Downloads\CTP-WireLakes\CTP-WireLakes-ERP-System-Blueprint.docx'
d = docx.Document(PATH)
paras = d.paragraphs

def find(prefix):
    for i, p in enumerate(paras):
        if p.text.strip().startswith(prefix):
            return p
    raise SystemExit('not found: ' + prefix)

TIERS = [
    'Fundi-Intern — a graduate who has NO industry experience and is looking for an internship. Interns are placed with Senior Fundis or partner organisations to gain hands-on experience.',
    'Attachment applicant — a student who has NOT yet graduated. Only students still in school can apply for industrial attachment; graduates cannot apply for attachment.',
    'Junior Fundi — a recent graduate who already has internship or attachment experience. Can take on supervised projects and simpler jobs.',
    'Senior Fundi — a person who has been in the industry for a long time. Fully eligible to take projects, mentor interns, and act as a referee for other applicants.',
]

def add_block(anchor, heading_text, intro_text, bullets, note_text=None):
    h = anchor.insert_paragraph_before('')
    r = h.add_run(heading_text)
    r.bold = True
    r.font.size = Pt(13)
    if intro_text:
        anchor.insert_paragraph_before(intro_text)
    for b in bullets:
        anchor.insert_paragraph_before(b, style='List Bullet')
    if note_text:
        p = anchor.insert_paragraph_before('')
        rr = p.add_run(note_text)
        rr.italic = True

# 1) Module 1: insert 4.4.1 before "4.5 Hirer / project owner registration workflow"
anchor = find('4.5 Hirer / project owner registration workflow')
add_block(
    anchor,
    '4.4.1 E-Fundi categories (tiers)',
    'Every registered E-Fundi is placed into one of four categories. The category determines what the person can apply for:',
    TIERS,
    'The category is selected on the Fundi registration form (Section 4.4), shown on the Fundi profile, and drives eligibility for attachment/internship placements and project matching in Modules 4, 6 and 7.'
)

# 2) Section 1.1 who-system-serves: insert the four tiers right after the E-Fundis bullet
anchor2 = find('E-Fundis — certified skilled workers')
for b in reversed(TIERS):
    anchor2.insert_paragraph_before(b, style='List Bullet')

d.save(PATH)
print('docx updated OK')
