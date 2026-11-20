# Kadvia Bot: setup aur roz ka kaam (simple Hinglish)

Kadvia Bot ek WordPress plugin hai. Yeh aapki website (kadvia.com) ke andar chalta hai, aur wp-admin mein **Kadvia Bot** naam ka menu aa jaata hai.

---

## 1. Bot kya karta hai, aur kya nahi

| Kaam | Bot kya karta hai | Aapko kya karna hai |
|---|---|---|
| Lead research | CSV import karta hai, duplicate hatata hai, email ka domain check karta hai | Apollo / Sales Navigator se list nikal kar CSV upload karna |
| Cold email | Har lead ke liye Claude AI se personal email likhta hai, follow-up bhejta hai, aur reply aate hi ruk jaata hai | Shuru mein har email padh kar **Approve** karna |
| Replies | Inbox padhta hai aur reply ko sort karta hai (interested, question, not interested, stop, out of office). Jawab ka draft bhi likh deta hai | Draft padh kar Approve karna |
| LinkedIn | Connection note aur follow-up message likh deta hai | Copy karke khud bhejna (LinkedIn bots allow nahi karta) |
| Calling | Har lead ke liye call script likh deta hai | Khud call karna, phir result mark karna |
| WhatsApp | Sirf opt-in logon ko Meta ka approved template bhejta hai | WhatsApp Business API account aur template banwana |
| Report | Har Monday client ko report email karta hai | Kuch nahi |
| Meeting | Interested lead ko booking link bhejta hai | Meeting fix ho jaaye to **Meeting booked** dabana |

**Safety, jo by default ON hai:**
- **Test mode (dry run):** kuch bhi asli mein nahi jaata. Pehle isi mode mein sab check karo.
- **Approval:** har email aapke OK ke baad hi jaata hai. Jo reply asli logon ko jaata hai, woh hamesha approval maangta hai.
- Har email mein aapka address aur unsubscribe link apne aap lagta hai. "STOP" likhne wale ko dobara kabhi email nahi jaata.
- Ek din mein limit (default 30), ek run mein 3, aur sirf office time (10 baje se 6 baje, Mon-Fri).

---

## 2. Kharcha (lagbhag)

| Cheez | Kharcha |
|---|---|
| Claude API | Claude Opus 5.5 par ek email lagbhag ₹2-6 ka padta hai. Settings mein Haiku 5.5 chuno to bahut sasta. Shuru mein $10-20 ka credit kaafi hai |
| Outreach domain | Ek alag domain (jaise trykadvia.com), lagbhag ₹800-1,000 saal ka |
| Google Workspace inbox | Lagbhag ₹150-300 mahina per inbox. Exact rate Google ki site par check karna |
| WhatsApp API (optional) | Meta per message charge karta hai, aur AiSensy/Interakt jaise partner ki monthly fee alag |

Yeh sab andaaza hai. Asli price har service ki website par check kar lena.

---

## 3. Install kaise karein

1. wp-admin → **Plugins → Add New → Upload Plugin** par jao.
2. `kadvia-bot.zip` choose karo → **Install Now** → **Activate**.
3. Left menu mein **Kadvia Bot** aa jaayega.

> PHP 8.1 ya usse naya version chahiye. Hostinger par: hPanel → Advanced → PHP Configuration → **8.2**.

---

## 4. Setup step by step

### Step 1: Claude API key
1. **platform.claude.com** par account banao.
2. **Billing** mein thoda credit daalo. Payment aap khud karna.
3. **API Keys → Create Key** se key banao aur copy karo.
4. Kadvia Bot → **Settings → 1. Claude** mein paste karo → **Save settings**.
5. Neeche **Test Claude** dabao. Ek sample email dikhe to key sahi lagi hai.

### Step 2: Email bhejne ka inbox (sabse zaroori)
Main domain kadvia.com se cold email **mat** bhejna. Spam mein gaya to aapki main email bhi kharab ho jaayegi.

1. Ek alag domain lo, jaise **trykadvia.com** ya **getkadvia.com**.
2. Us par Google Workspace inbox banao, jaise `divyansh@trykadvia.com`.
3. Domain ki DNS settings mein **SPF, DKIM aur DMARC** lagao. Google Workspace iske steps khud dikhata hai.
4. **2-3 hafte warm-up karo.** Roz 5-10 asli emails bhejo (dosto ko, apne doosre emails par) aur unke reply lo. Chaho to Instantly/Smartlead ka warm-up tool use kar sakte ho. Bot khud warm-up nahi karta.
5. Google account mein **2-Step Verification** ON karo, phir **App password** banao.

Phir Settings mein bharo:

| Field | Google Workspace ke liye |
|---|---|
| Sender name | Divyansh from Kadvia |
| Sender email | divyansh@trykadvia.com |
| Business postal address | Aapka asli office/ghar ka address |
| SMTP host / port / security | smtp.gmail.com / 587 / TLS |
| SMTP username / password | Poora email / App password |
| IMAP host / port | imap.gmail.com / 993 |
| IMAP username / password | Poora email / wahi App password |

Phir **Send a test email to myself** aur **Test inbox (IMAP)** dabao. Dono mein green message aana chahiye.

### Step 3: Campaign banao
Kadvia Bot → **Campaigns → + New campaign**
- **Client company:** jis client ke liye meeting book karni hai.
- **What does the client sell:** 2-3 line mein, saaf shabdon mein.
- **Who are we targeting:** designation, industry, company size, city.
- **Proof points:** sirf sach. Agar kuch nahi hai to khaali chhodo, AI apni taraf se kuch nahi banayega.
- **Signature:** naam, designation, company.
- **Channels:** Email, aur chaho to LinkedIn / Calls / WhatsApp bhi.
- **Active** par tick karo → **Save campaign**.

### Step 4: Leads daalo
Kadvia Bot → **Leads → Import CSV**
- Apollo ka export seedha chalega.
- Excel/Google Sheet mein yeh columns rakho: First Name, Last Name, Email, Company, Title, Phone, LinkedIn URL, City, Notes.
- WhatsApp ke liye "WhatsApp opt-in" column mein **yes** sirf unke liye likho jinhone permission di hai.
- Import ke baad har email ka domain check hota hai: ✓ sahi, ! shared inbox (info@, sales@), ✗ galat.

### Step 5: Test mode mein chalao
1. Dashboard → **Run the bot now** dabao.
2. **Approve** page par AI ke likhe emails padho. Theek lage to Approve karo. Badalna ho to edit karo ya "Rewrite with AI" dabao.
3. Test mode mein email "simulated" ho jaate hain. Har lead ki **conversation** mein dekh sakte ho ki kya jaata.
4. 10-20 emails dekh kar jab quality theek lage, tab aage badho.

### Step 6: Live karo
1. Settings → **Test mode** ka tick hatao → **Save**.
2. Pehle 2-3 hafte **approval ON** hi rakho.
3. Daily limit pehle hafte 20, phir dheere-dheere 30-40 karo. Ek inbox se 50 se zyada kabhi mat bhejna. Zyada volume chahiye to 2-3 inbox banao.

### Step 7: Hostinger par asli cron (zaroori)
WordPress ka cron tabhi chalta hai jab koi site khole. Bot har 10 minute chale, iske liye:
1. hPanel → **Advanced → Cron Jobs** par jao.
2. Type: **Custom**, aur yeh command daalo (kadvia.com ki jagah aapka domain):
   ```
   wget -q -O - "https://kadvia.com/wp-cron.php?doing_wp_cron" >/dev/null 2>&1
   ```
3. Har 10 minute ke liye set karo: minute field mein `*/10`, baaki sab `*`.

---

## 5. Roz ka 15 minute ka kaam

1. **Approve:** naye emails aur reply ke drafts padho, phir Approve karo.
2. **Replies:** interested logon ko dekho. Meeting fix ho jaaye to **Meeting booked** dabao.
3. **LinkedIn & Calls:** text copy karke LinkedIn par bhejo ya call karo, phir result mark karo.
4. **Dashboard:** "failed" dikhe to Activity log mein reason padho.

---

## 6. Zaroori niyam (inhe mat todna)

- WhatsApp **sirf unhe** jinhone khud permission di ho. Warna Meta number ban kar deta hai.
- India mein calling ke liye DND/TRAI rules maano. DND wale number par promotional call mat karo.
- Kisi ne "stop" bola to bot use apne aap list se hata deta hai. Use manually wapas mat daalna.
- AI ka likha har email padh kar hi approve karo, khaaskar shuru mein.
- Client ko meetings ki guarantee mat do. Pehla mahina pilot hota hai.

---

## 7. Problem aaye to

| Dikkat | Kya karein |
|---|---|
| "Claude API key is wrong" | Key dobara copy karo, aur Billing mein credit check karo |
| Test email nahi jaata | App password sahi hai? Port 587 + TLS? |
| Replies nahi aa rahe | Test inbox (IMAP) dabao. Bot sirf connect hone ke **baad** aayi mail padhta hai |
| Emails spam mein jaa rahe | Limit kam karo, warm-up continue rakho, SPF/DKIM/DMARC check karo, links kam rakho |
| Bot chal hi nahi raha | Hostinger cron (Step 7) lagao. Dashboard par "Last run" ka time dekho |

Koi screen samajh na aaye to us screen ka screenshot bhej dena.
