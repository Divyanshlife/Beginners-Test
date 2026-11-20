<?php
/**
 * Six starter blog posts for Kadvia. They are created once by the theme
 * (see dp_create_blog() in auto-setup.php). Edit or delete them any time
 * from Posts in wp-admin.
 *
 * @package divyansh-pro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function dp_starter_posts() {
	$cta = '<p><strong>Want meetings like these on your calendar?</strong> <a href="/contact/">Book a free 20-minute strategy call</a> with Kadvia.</p>';

	return array(

		/* ------------------------------------------------------------ */
		array(
			'slug'    => 'what-is-appointment-setting',
			'title'   => 'What is appointment setting? A plain-English guide for B2B teams',
			'cat'     => 'Appointment Setting',
			'image'   => 'what-is-appointment-setting.jpg',
			'excerpt' => 'Appointment setting means someone else does the prospecting and books qualified sales meetings for your team. Here is how it works, what it costs you in time, and when it makes sense.',
			'content' => <<<'HTML'
<p>Every B2B sale starts with a meeting. Before a proposal, a demo or a negotiation, someone has to get a busy decision-maker to agree to a conversation. <strong>Appointment setting</strong> is the work of making that happen, again and again, so your sales team spends its time selling instead of chasing.</p>

<h2>What an appointment setter actually does</h2>
<p>A good appointment-setting process has four parts:</p>
<ol>
<li><strong>Define the buyer.</strong> Which industries, company sizes and job titles are worth talking to, and what problem do they have that you solve?</li>
<li><strong>Build the list.</strong> Research the right accounts and find verified contact details for the people who decide.</li>
<li><strong>Start conversations.</strong> Reach out through email, LinkedIn and phone with messages written for that buyer, not a generic template.</li>
<li><strong>Qualify and book.</strong> When someone is interested, check they are a fit, then put a meeting on your calendar with a short brief for the person taking the call.</li>
</ol>

<h2>Appointment setting vs lead generation</h2>
<p>Lead generation usually ends with a name, an email or a form fill. Appointment setting goes one step further: it ends with a <em>confirmed meeting</em> with a person who has agreed to talk. That difference matters, because the hardest part of outbound sales is not finding names; it is getting a reply.</p>

<h2>When it makes sense</h2>
<ul>
<li>Your deal size is large enough that one good meeting is worth real money.</li>
<li>Your team closes well once they are in the room, but the calendar is not full.</li>
<li>Referrals were your main source of new business and they are slowing down.</li>
<li>You want to test a new market or industry without hiring a full team first.</li>
</ul>

<h2>When it does not</h2>
<p>If your product is low-priced and sold online, a sales meeting may cost more than it earns. And if nobody on your side can attend and close the meetings, booking them will not help. Fix that first.</p>

<h2>What to ask before hiring a partner</h2>
<ul>
<li>Who exactly will you contact, and how do you research them?</li>
<li>Which channels do you use, and who writes the messages?</li>
<li>How do you define a "qualified" meeting?</li>
<li>What do I see every week, and who owns the data?</li>
</ul>
<p>Clear answers to these four questions tell you more than any promise of numbers.</p>
HTML
			. $cta,
		),

		/* ------------------------------------------------------------ */
		array(
			'slug'    => 'cold-email-inbox-rules',
			'title'   => 'Cold email that lands in the inbox: 9 rules we follow',
			'cat'     => 'Cold Email',
			'image'   => 'cold-email-inbox-rules.jpg',
			'excerpt' => 'A great cold email is useless if it lands in spam. These nine rules cover the setup, the list and the writing that keep your emails in the main inbox.',
			'content' => <<<'HTML'
<p>Most cold email problems are not writing problems. They are delivery problems. If your message goes to spam, nobody reads it, however good it is. These are the rules we follow on every campaign.</p>

<h2>Setup</h2>
<ol>
<li><strong>Never send from your main domain.</strong> Use a separate domain that looks like yours (for example, getyourbrand.com) so your company email stays safe.</li>
<li><strong>Set up SPF, DKIM and DMARC.</strong> These three records prove to inbox providers that you are allowed to send from that domain. Your domain host can help you add them.</li>
<li><strong>Warm up new inboxes.</strong> A brand-new inbox that suddenly sends hundreds of emails looks like a spammer. Start small and increase slowly over a few weeks.</li>
<li><strong>Keep daily volume low per inbox.</strong> It is safer to spread sending across several inboxes than to push one inbox hard.</li>
</ol>

<h2>The list</h2>
<ol start="5">
<li><strong>Verify every email address.</strong> Bounces hurt your sender reputation fast. Clean the list before you send, not after.</li>
<li><strong>Write to fewer, better-fit people.</strong> A short list of the right buyers gets more replies and fewer spam complaints than a big list of strangers.</li>
</ol>

<h2>The writing</h2>
<ol start="7">
<li><strong>Keep it short and plain.</strong> No images, no attachments, one link at most. Read it on your phone: if you need to scroll, cut it.</li>
<li><strong>Make it about them.</strong> One line that shows you know who they are, one line about the problem you solve, one simple question.</li>
<li><strong>Ask for a reply, not a meeting.</strong> "Is this a priority for you this quarter?" is easier to answer than "Can we book 30 minutes?"</li>
</ol>

<h2>A simple template</h2>
<blockquote><p>Hi Priya, saw that Acme opened a second plant in Pune this year. When teams expand like that, vendor onboarding usually gets messy. We help operations heads cut that time in half. Is this on your list for this quarter?</p></blockquote>
<p>Short, specific and easy to answer. Change the details for every account, and always follow your local rules on commercial email, including a clear way to opt out.</p>
HTML
			. $cta,
		),

		/* ------------------------------------------------------------ */
		array(
			'slug'    => 'linkedin-outreach-playbook',
			'title'   => 'LinkedIn outreach that gets replies: a 5-step playbook',
			'cat'     => 'LinkedIn',
			'image'   => 'linkedin-outreach-playbook.jpg',
			'excerpt' => 'LinkedIn works when it feels like a conversation, not a sales pitch. This five-step playbook shows how to go from profile to booked meeting without being pushy.',
			'content' => <<<'HTML'
<p>LinkedIn is where your buyers already are, with their job title and company right on the profile. That makes it one of the best channels for B2B outreach, as long as you treat it like a conversation and not a billboard.</p>

<h2>Step 1: Fix your own profile first</h2>
<p>Before anyone accepts your request, they look at your profile. Use a clear photo, a headline that says who you help ("Helping IT services firms book meetings with CTOs"), and a short About section that ends with how to contact you.</p>

<h2>Step 2: Choose a small, specific list</h2>
<p>Pick one industry and one or two job titles. Twenty well-chosen people you have researched will do better than two hundred random ones. Note something specific about each: a recent post, a new role, a company announcement.</p>

<h2>Step 3: Connect without a pitch</h2>
<p>Keep the connection note short and personal, or send none at all. Never pitch in the request.</p>
<blockquote><p>Hi Rahul, enjoyed your post on hiring for remote teams. I work with staffing firms in Bangalore and would be glad to connect.</p></blockquote>

<h2>Step 4: Start a real conversation</h2>
<p>After they accept, wait a day, then send one message that is about them. Ask a question about their work. Share something useful: a short checklist, an article, an observation. Give before you ask.</p>

<h2>Step 5: Make the ask simple</h2>
<p>When the conversation is warm, suggest a short call with a clear reason: "Would a 20-minute call to compare notes on this be useful?" If they say not now, thank them and stay in touch. Many good meetings come months later.</p>

<h2>Three mistakes to avoid</h2>
<ul>
<li>Sending a long sales message right after they connect.</li>
<li>Copy-pasting the same text to everyone. People can tell.</li>
<li>Sending too many requests a day. Stay well within LinkedIn's limits and its user agreement.</li>
</ul>
HTML
			. $cta,
		),

		/* ------------------------------------------------------------ */
		array(
			'slug'    => 'in-house-sdr-vs-outsourced',
			'title'   => 'In-house SDR or outsourced appointment setting? How to decide',
			'cat'     => 'Sales Strategy',
			'image'   => 'in-house-sdr-vs-outsourced.jpg',
			'excerpt' => 'Hiring your own sales development rep and using a partner both work. The right choice depends on time, control and what you already have. Here is a simple way to compare.',
			'content' => <<<'HTML'
<p>Once you decide you need more sales meetings, the next question is who does the prospecting. You can hire a sales development representative (SDR) or work with an appointment-setting partner. Both can work. Here is how to think about it.</p>

<h2>What an in-house SDR really needs</h2>
<ul>
<li><strong>Hiring time:</strong> finding and interviewing the right person often takes weeks.</li>
<li><strong>Ramp-up:</strong> a new SDR needs time to learn your product, your buyers and your messaging before results come in.</li>
<li><strong>Tools and data:</strong> a contact database, email tools, a CRM and a LinkedIn plan all cost money every month.</li>
<li><strong>Management:</strong> someone has to coach, review messages and check numbers every week.</li>
<li><strong>Risk:</strong> if the person leaves, much of that learning leaves with them.</li>
</ul>

<h2>What a partner brings</h2>
<ul>
<li>A team, process and tools that are already set up.</li>
<li>A faster start, often within a few weeks.</li>
<li>Experience from many campaigns about what gets replies.</li>
<li>Easy to scale up or down without hiring or letting people go.</li>
</ul>

<h2>A simple way to decide</h2>
<table>
<thead><tr><th>Choose in-house if...</th><th>Choose a partner if...</th></tr></thead>
<tbody>
<tr><td>You already have a sales manager who can coach</td><td>Nobody has time to manage a new hire</td></tr>
<tr><td>Your product needs deep technical knowledge to explain</td><td>Your offer is clear and easy to explain in two lines</td></tr>
<tr><td>You are building a long-term sales team</td><td>You want to test a market before investing more</td></tr>
<tr><td>You can wait a few months for results</td><td>You need meetings starting this quarter</td></tr>
</tbody>
</table>

<h2>The hybrid option</h2>
<p>Many companies start with a partner to learn what works (which buyers reply, which messages land), then hire in-house later with a proven playbook. That way the first hire does not have to figure everything out alone.</p>
HTML
			. $cta,
		),

		/* ------------------------------------------------------------ */
		array(
			'slug'    => 'ideal-customer-profile-worksheet',
			'title'   => 'Write your ideal customer profile in 30 minutes',
			'cat'     => 'Sales Strategy',
			'image'   => 'ideal-customer-profile-worksheet.jpg',
			'excerpt' => 'Outbound only works when you know exactly who to reach. Use this short worksheet to write an ideal customer profile your whole team can use.',
			'content' => <<<'HTML'
<p>An <strong>ideal customer profile (ICP)</strong> describes the type of company that gets the most value from what you sell, and is the easiest for you to win. Without one, outreach turns into guesswork. With one, every list, message and call gets sharper.</p>

<h2>Step 1: Look at your best five clients (10 minutes)</h2>
<p>Pick the clients who pay well, stay long and are easy to work with. For each, write down:</p>
<ul>
<li>Industry and what they sell</li>
<li>Company size (team size or yearly revenue)</li>
<li>City or country</li>
<li>Who signed the deal, and who used your product or service</li>
<li>What was happening in their business when they bought</li>
</ul>

<h2>Step 2: Find the pattern (10 minutes)</h2>
<p>Circle what repeats. Maybe four of the five are IT services firms with 50 to 200 people. Maybe the buyer is always the head of operations. Maybe they all bought right after raising money or opening a new office. That repeat is your ICP.</p>

<h2>Step 3: Write it in one paragraph (10 minutes)</h2>
<blockquote><p>We help <em>IT services companies in India with 50 to 200 employees</em> who are <em>growing their client base abroad</em>. The decision-maker is usually the <em>founder or head of sales</em>. They come to us when <em>referrals slow down and the team has no time to prospect</em>.</p></blockquote>

<h2>Add buying signals</h2>
<p>Signals tell you <em>when</em> to reach out. Common ones are hiring for sales roles, opening a new office, launching a product, a new leader joining, or recent funding. Reaching out at the right moment can matter as much as reaching the right person.</p>

<h2>Keep it alive</h2>
<p>Review your ICP every quarter. Look at who replied, who booked and who bought, then update it. It is a working document, not a one-time exercise.</p>
HTML
			. $cta,
		),

		/* ------------------------------------------------------------ */
		array(
			'slug'    => 'follow-up-messages-guide',
			'title'   => 'Follow-up messages: how many, how often, and what to say',
			'cat'     => 'Cold Email',
			'image'   => 'follow-up-messages-guide.jpg',
			'excerpt' => 'Most people stop after one message. A polite, useful follow-up sequence is often where the replies come from. Here is a simple plan you can copy.',
			'content' => <<<'HTML'
<p>Busy people miss messages. They read your email on the phone between meetings, mean to reply and forget. That is why a short, polite follow-up sequence is one of the easiest ways to get more replies without contacting more people.</p>

<h2>How many follow-ups?</h2>
<p>Three to four follow-ups after the first message is a good range for B2B outreach. Fewer and you give up too early; many more and you start to annoy people.</p>

<h2>How often?</h2>
<ul>
<li><strong>Day 1:</strong> first message</li>
<li><strong>Day 3 or 4:</strong> follow-up 1, a short nudge</li>
<li><strong>Day 8:</strong> follow-up 2, add something useful</li>
<li><strong>Day 14:</strong> follow-up 3, try a different angle or channel</li>
<li><strong>Day 21 to 30:</strong> a polite final note</li>
</ul>

<h2>What to say in each one</h2>
<p><strong>Nudge:</strong> one line, same thread. "Hi Neha, bringing this to the top of your inbox. Is this something you are looking at this quarter?"</p>
<p><strong>Add value:</strong> share a short tip, a case example or a question that shows you understand their world. Do not just repeat the first email.</p>
<p><strong>Change the angle:</strong> try a different problem you solve, or reach out on LinkedIn or by phone instead of email.</p>
<p><strong>The polite close:</strong> "I will not keep filling your inbox. If this becomes a priority later, just reply to this email and I will pick it up." This one often gets the most honest replies.</p>

<h2>Rules that keep follow-ups friendly</h2>
<ul>
<li>Stop the moment someone replies, even with a no.</li>
<li>Never guilt-trip ("I have emailed you four times...").</li>
<li>Keep every message shorter than the one before.</li>
<li>Always make it easy to say "not interested".</li>
</ul>
HTML
			. $cta,
		),
	);
}
