-- Migration 060: enrich "How to Join the Uganda Astronomical Society" (article id 4).
-- Replaces the two-paragraph stub with the full flagship treatment in the
-- shared structure (scene -> stake -> idea -> Uganda -> move -> sign-off)
-- plus a cover image. Plain UPDATE on the stable id is naturally idempotent.

UPDATE articles SET
  body = '<p>After every public talk I give, there is a moment I have come to recognise. The crowd thins, the telescopes get packed away, and one person lingers at the edge — turning something over in their mind. Eventually they walk up and ask, quietly, as if it might be a foolish question: "So… how does someone like me join all this?"</p>
<p>Someone like you joins exactly the way the rest of us did: by deciding that curiosity counts as a qualification. This article is the full answer I wish I had time to give every time — which class fits you, what it costs, what happens after you register, and what membership actually gets you.</p>
<h2>The short answer</h2>
<p>Three steps. First, open the <strong>Join page</strong> and pick the membership class that fits you. Second, register with your name and email. Third, watch your inbox: a real person reviews every application, and when you are approved you receive a confirmation email from our membership desk with your membership number. Then you sign in, and you are one of us.</p>
<p>Registration takes about five minutes. Approval usually takes a day or two. Your membership number looks like UAS-2026-0143 — keep it; it is yours for as long as you are with us, and you will quote it whenever you write to us.</p>
<h2>Which class is yours</h2>
<p>There are six classes, and the honest differences are price and purpose — every member looks through the same telescopes.</p>
<p><strong>Student (free).</strong> If you are enrolled anywhere — primary, secondary, university — this is yours, and it costs nothing. Full access to observing sessions, the knowledge base, the community forum, the mentorship programme, and discounted workshops. We mean it when we say students are the future of this society: the door is open, walk through it.</p>
<p><strong>Affiliate (UGX 20,000 a year).</strong> For individual supporters who want to belong and back the work — observing sessions, knowledge base, forum, workshop discounts.</p>
<p><strong>Regular (UGX 35,000 a year).</strong> For the enthusiast. Everything above, plus working-group access, voting rights at society decisions, and research collaboration. If you are an adult who looks up and wonders, this is almost certainly your class.</p>
<p><strong>Institutional (UGX 100,000 a year).</strong> For schools, universities, and organisations — bulk membership for your people, priority event access, research partnership, and institutional recognition. Write to us through the contact page and we will set it up with you.</p>
<p><strong>Corporate (UGX 150,000 a year).</strong> For companies that want to stand behind Ugandan science — brand visibility at events, bulk membership, priority access, research partnership, and an annual impact report. Also arranged through the contact page.</p>
<p><strong>Honorary.</strong> Not applied for but bestowed, for distinguished contributors to astronomy in Uganda. If you are reading this wondering whether it means you — it probably does not work that way, and that is fine. Come as a Regular and make us notice.</p>
<h2>What happens after you register</h2>
<p>Your application lands in front of a human being — usually within a day. We check that the details are real and the class fits. Then one of two emails arrives from the membership desk.</p>
<p>If you are approved, the email carries your membership number and a link to sign in. From that moment the whole member side of the platform opens: event RSVPs, the member directory, your dues record, working groups. If your class carries annual dues, your dues record appears under Dashboard, and paying it keeps you in good standing.</p>
<p>If you are not approved, the email says so plainly, with an invitation to reply and ask why. Sometimes it is a duplicate application, sometimes a wrong class, sometimes we just need one more detail. A rejection is a conversation, not a verdict — answer it.</p>
<h2>What membership actually gets you</h2>
<p>The register button is the beginning, not the product. Here is what members do that guests cannot: reserve places at observing nights and join waitlists when they fill; attend <strong>members-only events</strong>, which never appear on the public pages at all; submit articles to Knowledge and propose events of your own; appear in the member directory; join working groups and committees; and vote, if you are a Regular member, on the decisions that steer the society.</p>
<p>Guests are always welcome at public events — sign up with just a name and email. But membership is how you move from audience to participant.</p>
<h2>The question behind the question</h2>
<p>When that person lingers after the talk and asks how to join, they are rarely asking about forms. They are asking whether they belong — whether you need a degree, or equipment, or to already know the constellations. So let me answer that question too, as clearly as I can.</p>
<p>You need none of those things. You need curiosity and a willingness to show up. Bring your children; the eleven-year-old asking about Jupiter tonight is the programme lead of 2040. Bring your doubts; half of us started as skeptics. The sky does the rest.</p>
<p>We will see you at Kololo.</p>
<p><em>— Christopher Byaruhanga Malcom, Uganda Astronomical Society</em></p>',
  image_url = 'img/outreach-school-telescope.jpg'
WHERE id = 4;
