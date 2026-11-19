# Kadvia: WordPress + Elementor setup guide

Is folder mein kya hai:

| File | Kya hai |
|---|---|
| `divyansh-pro.zip` | WordPress theme (header, footer, colors, fonts) |
| `elementor-templates.zip` | 5 ready pages ka design: Home, About, Services, Industries, Contact |
| `preview/home-preview.html` | Home page ka preview (browser mein kholke dekho) |
| `divyansh-pro/`, `elementor-templates/` | Upar wali zip files ke andar ka saman (edit karne ke liye) |

---

## Step 1: Theme install karo
1. WordPress admin kholo (`yoursite.com/wp-admin`).
2. **Appearance → Themes → Add New Theme → Upload Theme**.
3. `divyansh-pro.zip` choose karo → **Install Now** → **Activate**.

## Step 2: Elementor plugin install karo
1. **Plugins → Add New Plugin** → search karo **Elementor** → **Install Now** → **Activate**.
2. Free version kaafi hai.

## Step 3: 5 page designs import karo
1. **Templates → Saved Templates** (ya **Elementor → Templates**) kholo.
2. Upar **Import Templates** button dabao.
3. `elementor-templates.zip` choose karo → **Import Now**.
4. Ab list mein 5 templates dikhenge: "Kadvia - Home", "- About" etc.

## Step 4: 5 pages banao
Har page ke liye yeh karo (Home, About, Services, Industries, Contact):
1. **Pages → Add New Page**. Title likho (jaise `About`). Page ka link `/about/` banna chahiye.
2. **Edit with Elementor** dabao.
3. Neeche folder wala icon (**Add Template**) dabao → **My Templates** tab → us page ka template → **Insert**.
4. Left side mein gear icon (**Page Settings**) → **Page Layout** = **Elementor Full Width**.
5. **Publish** dabao.

Page links aise rakho: `/about/`, `/services/`, `/industries/`, `/contact/`. Buttons inhi links par jaate hain.

## Step 5: Home page set karo
**Settings → Reading → Your homepage displays → A static page** → Homepage = **Home** → **Save**.

## Step 6: Menu banao (optional)
Jab tak menu nahi banate, header mein 5 pages ke links apne aap dikhte hain.
Apna menu banana ho to: **Appearance → Menus** → naya menu → pages add karo → location **Header Menu** tick karo → Save.

---

## Kuch bhi kaise badlein

**Text, button, photo:** page kholo → **Edit with Elementor** → jis text par click karo, wahi edit ho jayega → **Update**.

**Naya card ya section:** kisi card par right-click → **Duplicate**. Phir text badal do. Design apne aap same rahega.

**Colors aur contact details (poori site par):** **Appearance → Customize → Kadvia Settings**:
- Accent color, dark color, background color
- Header button ka text aur link
- Footer ka intro, email, phone, WhatsApp, location, LinkedIn

**Logo / site naam:** **Appearance → Customize → Site Identity**. Logo upload karo, ya Site Title mein `Divyansh Arora` likho.

**Contact form chahiye?** Free option: **WPForms Lite** ya **Contact Form 7** plugin install karo, form banao, aur Contact page par Elementor ka **Shortcode** widget daal ke form ka shortcode paste karo.

## Design ki "magic" classes
Har section/card par design CSS classes se aata hai (Elementor → element select karo → **Advanced → CSS Classes**). Naya element banao to yeh classes laga do:

| Class | Kahan lagao | Kya karti hai |
|---|---|---|
| `dp-sec` | Section | Upar-neeche badi spacing |
| `dp-alt` | Section | White band (alag background) |
| `dp-dark` | Section | Blue-pink gradient band (CTA ke liye) |
| `dp-hero` | Section | Hero ka blue-pink glow |
| `dp-card` | Column | White card with border |
| `dp-eyebrow` | Heading | Chhota pink label |
| `dp-h1` / `dp-h2` / `dp-h3` | Heading | Bada / medium / card title |
| `dp-lead` / `dp-text` / `dp-small` | Text Editor | Bada / normal / chhota text |
| `dp-btn` / `dp-btn-ghost` | Button | Blue pill / outline pill button |
| `dp-list` | Icon List | Blue tick list |
| `dp-faq` | Accordion | FAQ style |

## Zaroori baat
Yeh design Zillion Systems jaisi feel (clean, professional, serif headings, cards) par bana hai, lekin unka naam, logo, text ya photos use nahi kiye. Saara content aapka apna hai.

## Kadvia update (version 2.0)
Purani site par nayi theme upload karo (**Replace active with uploaded**), phir **Dashboard** kholo. Theme apne aap:
- Site ka naam **Kadvia** aur tagline **Your way to booked meetings** kar degi
- Solutions page ko **Services** bana degi
- 5 pages mein naya Kadvia design daal degi (purana text badal jayega)

**"Blessings always Guruji"** line har page ke header (sabse upar) aur footer (beech mein) aati hai. Text badalna ya hatana ho: **Appearance → Customize → Kadvia Settings → Blessing line**.

## Logo aur browser icon (version 2.4: navy K-in-D logo, Montserrat bold)
`brand/` folder mein:
| File | Kahan use karein |
|---|---|
| `kadvia-logo.png` | Visiting card, email signature, documents (white background) |
| `kadvia-logo-white.png` | Dark ya blue background par |
| `kadvia-logo-black.png` | Print aur documents ke liye |
| `kadvia-icon-512.png` | WordPress Site Icon (browser tab) |
| `kadvia-profile-1080.png` | WhatsApp / LinkedIn profile photo |
| `kadvia-wordmark.png` | Website header |
| `logo-studio.html` | Logo editor (browser mein kholo) |

Theme 2.1 mein header aur browser tab mein Kadvia icon apne aap aata hai. Alag icon chahiye to: **Appearance → Customize → Site Identity → Site Icon**.
