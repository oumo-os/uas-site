-- Migration 059: three flagship articles (founder story, education reflection,
-- institution-building argument). Written in a shared, copyable structure:
-- scene -> personal stake -> idea in depth -> what it means for Uganda ->
-- your move -> sign-off. See the guide ("Anatomy of a good UAS article").
-- IDEMPOTENT: each INSERT runs only if the title is absent.

-- How a Telescope and a WhatsApp Group Became Uganda's Astronomical Society (author_id 2)
INSERT INTO articles (author_id, title, body, category, tags, image_url, status, approved_by, approved_at, published_at)
SELECT 2, 'How a Telescope and a WhatsApp Group Became Uganda''s Astronomical Society', '<p>It was past nine on an ordinary Kampala night when a boy of maybe eleven squeezed to the front of the small crowd around my telescope, pointed at the brightest thing in the sky, and asked me the question I have now heard a hundred times: "Is that a star, or is somebody up there?"</p>
<p>It was Jupiter. I told him so, and then I did what I always do — I stepped aside and let him look. He went quiet in the particular way children go quiet when the universe rearranges itself in front of them. His mother laughed and said he would talk about nothing else for a month. She was probably right. That is how all of this started: one person looking, going quiet, and then telling someone else.</p>
<h2>It did not begin with a plan</h2>
<p>In 2023 I did not set out to found anything. There was no funding, no office, no strategic document. There was a telescope, a city full of people who had never looked through one, and a simple observation: Ugandans are curious about the night sky. You only have to set up on any reasonably dark evening and wait. They come to you.</p>
<p>So we held sidewalk sessions. Someone would ask when the next one was, and I would give them my number. They would bring a cousin. The cousin would bring a neighbour. At some point the list of numbers became a WhatsApp group, and at some point after that, the WhatsApp group needed a name. Uganda Astronomical Society. It sounded far grander than we were. I liked that. It gave us something to grow into.</p>
<h2>The nights that taught us</h2>
<p>September 2025 gave us the total lunar eclipse, and Kampala looked up together. A month later, World Space Week took us to Makerere University, and the turnout told us this was bigger than a hobby club. Early in 2026, our social media pages filled with new members faster than we could welcome them.</p>
<p>Then came La Brise in May 2026 — a full stargazing weekend at the resort, our biggest undertaking yet. It worked. People came, the skies cooperated, and something real happened out there. It also nearly broke us. Transport, money, equipment, volunteers: everything that a weekend of that size demands, we had to invent on the spot. I will be honest with you — we made mistakes you could see from space. A society that wants to last cannot run on adrenaline and goodwill alone. La Brise proved the demand and exposed the gaps in the same weekend. That is the most valuable thing an event has ever given us.</p>
<h2>Why a society, and not just a hobby</h2>
<p>Hobbies depend on enthusiasts. Enthusiasts get tired, move away, find new interests. A society outlives all of that. Telescopes need keepers. Knowledge needs a home where a twelve-year-old from this year can find what a twelve-year-old from last year learned. The boy asking about Jupiter will be grown before Uganda''s space story is finished being written — somebody has to keep the page open for him.</p>
<p>That is what Stage V means to me. Formation, discovery, expansion, proof — we have lived all of that. Now comes the unglamorous part: registers and roles, dues and audits, programmes with actual leads. It does not photograph as well as an eclipse. It matters more.</p>
<h2>What comes next</h2>
<p>Every first Friday of the month, we set up at Kololo and look up together. The programmes are growing — observing, education, outreach, the ecosystem work. There is a place in all of it with your name on it, whether you own a telescope or have never touched one.</p>
<p>Come and look. Go quiet for a moment. Then tell someone else. That is still the whole strategy.</p>
<p><em>— Obwengye Cosmus, Founder, Uganda Astronomical Society</em></p>', 'article', '["founding","community","uas-story"]', 'img/cosmus-telescope-child.jpg', 'published', NULL, NOW(), NOW()
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM articles WHERE title = 'How a Telescope and a WhatsApp Group Became Uganda''s Astronomical Society');

-- The Classroom Under the Sky: What Our Teenagers Taught Us About Space (author_id 3)
INSERT INTO articles (author_id, title, body, category, tags, image_url, status, approved_by, approved_at, published_at)
SELECT 3, 'The Classroom Under the Sky: What Our Teenagers Taught Us About Space', '<p>It was 6:40 in the evening, forty minutes into our online session, when the chat window exploded. Two hundred teenagers, most of them on phones, most of them supposed to be doing homework. One message kept getting copied and pasted until it filled the screen: "Sir, is there a Ugandan satellite watching us RIGHT NOW?"</p>
<p>I laughed before I answered. Then I told them the truth, which is always the best part of teaching: not yet — but the sky above Uganda is busier than you think, and one day the satellite might be yours.</p>
<h2>Why a doctor cares about satellites</h2>
<p>People ask me this often. By day I work in medicine and biotechnology. My conviction is simple: a satellite is a public-health instrument that happens to fly. The same Earth observation that tracks a maize field tracks a flood. The same communications that carry a classroom lesson carry a diagnosis. Space is not an escape from Uganda''s problems. It is a vantage point over them.</p>
<p>That is the lens I brought to our high-school space programme with Nileorbital Aerospace — five evenings, 5 to 7 PM, ages thirteen to nineteen, three big ideas and no jargon allowed.</p>
<h2>Three ideas, no jargon</h2>
<p>First, <strong>satellite systems</strong>: a satellite is a phone in the sky with solar panels for a charger. It goes around and around because falling and missing the Earth forever turns out to be useful. Once a student pictures that, orbits stop being magic.</p>
<p>Second, <strong>observe</strong>: we pulled up what Uganda looks like from above and watched the room go silent. Your district, your trading centre, the swamp behind the school — all of it visible, measurable, changing year by year. Flood mapping, crop monitoring, city growth: these are not foreign technologies. They are photographs of home, read properly.</p>
<p>Third, <strong>launch dynamics</strong>: rockets do not go straight up, and the moment the students understood why — that turning sideways is how you stay up — you could feel two hundred brains click at once. Propulsion, trajectories, mission profiles: suddenly these were puzzles, not vocabulary.</p>
<h2>The girls asked the hardest questions</h2>
<p>I want to say this plainly because it mattered. Our guest sessions brought in engineers the students could see themselves in — including Eng. Patience Namugwanya, a space systems engineer working on satellite hardware in Dublin and an analog astronaut, who spoke to them about satellite communications. And young women like Rosmery Nalwanga, an AI and embedded systems engineer on Uganda''s own CLIMCAM project, showing that the path from a Kampala classroom to flight hardware is walkable.</p>
<p>The girls in our sessions asked the questions that made me pause the slides. Keep going. The industry needs the people who ask twice.</p>
<h2>What teenagers understand that adults forget</h2>
<p>Adults want space to be impressive. Teenagers want it to be <em>testable</em>. Why does the rocket turn? What happens if the solar panel breaks? Who decides where the satellite looks? Every "why" peeled back another layer, and nobody was embarrassed to ask. Curiosity is a method, not a mood. Our job as educators is to protect it until it becomes competence.</p>
<h2>What this means for Uganda</h2>
<p>Two hundred teenagers now know what an orbit is, what Earth observation is for, and what a launch trajectory costs. Some of them will forget it by Christmas. Some of them will not forget it ever, and in ten years one of them will be sitting where I sit, telling another chat window full of teenagers that the satellite might be theirs. That is how a space programme is actually built — not announced, but handed down, one cohort at a time.</p>
<p>Schools and parents: our doors are open. Partnerships, like the one with Nileorbital that made these evenings possible, are how we reach every district. And students: the next cohort is coming. Bring your hardest questions.</p>
<p><em>— Christopher Byaruhanga Malcom, Uganda Astronomical Society</em></p>', 'educational', '["education","youth","stem","nileorbital"]', 'img/event-nileorbital-program.jpg', 'published', NULL, NOW(), NOW()
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM articles WHERE title = 'The Classroom Under the Sky: What Our Teenagers Taught Us About Space');

-- The Unglamorous Work of Reaching Orbit (author_id 1)
INSERT INTO articles (author_id, title, body, category, tags, image_url, status, approved_by, approved_at, published_at)
SELECT 1, 'The Unglamorous Work of Reaching Orbit', '<p>It was past one in the morning and I was reconciling dues records — matching payments to names to membership numbers, line by line, in a quiet house while the rest of Kampala slept. Nobody writes songs about a membership register. Nobody points at a spreadsheet and feels wonder. But I have come to believe something unfashionable: nothing flies without the boring parts. Every rocket you have ever admired sat on top of a mountain of paperwork, and every spacefaring nation got there by building institutions first and rockets second.</p>
<p>I build systems for the Uganda Astronomical Society — the website you are reading, the membership tools, the registers. This is an article about why that work matters as much as any telescope. Possibly more.</p>
<h2>Rockets are the tip</h2>
<p>Picture the launch you remember best. Now picture everything underneath it that the cameras never show: the frequency filing that kept the telemetry legal, the insurance contract, the range-safety rules, the university department that trained the guidance engineer, the tax code that let the startup exist, the debris policy that keeps the orbit usable for the next launch. A launch is the visible ten percent. The other ninety is institutions — agreements, records, standards, money handled cleanly.</p>
<p>Uganda will not reach orbit by skipping that ninety percent. Nobody has. The countries making real moves in space right now — and there are African ones among them — all did the unglamorous work: a space agency with a mandate, a registry, a budget line, a strategy document that survives elections. Inspiration is fuel. Institutions are the engine.</p>
<h2>What we are actually building</h2>
<p>So here is what my late nights are for. A membership register where every member has a number and every number traces to a real person — because a society that cannot count its members cannot represent them. A finance system where every shilling of dues and every expense is recorded, approved, and auditable — because donated trust is the only currency a young institution has, and it spends fast. Governance with terms and handover, so the society outlives its founders. Programmes with named leads, so work does not depend on whoever happens to be awake at 1am.</p>
<p>None of this will ever trend. All of it is load-bearing. When a partner — a university, a ministry, an international agency — asks who we are, the answer is not our enthusiasm. The answer is our books.</p>
<h2>Boring is a compliment</h2>
<p>I have a test I apply to everything I build for UAS: could a stranger audit it? If the answer is yes, it ships. A dues receipt is a tiny act of statecraft. A published meeting minute is a promise kept in public. World Space Week, October 4 to 10, is our next forcing function — a national deadline that will test whether our systems hold under real load. I welcome the test. Systems prove themselves in October, not in strategy documents.</p>
<h2>Where you fit</h2>
<p>Here is the part people miss: you do not need a telescope to build a space programme. We need accountants more urgently than we need eyepieces. We need lawyers who understand contracts, teachers who can hold a classroom''s attention, coders, writers, photographers, drivers, organisers — the full inventory of ordinary excellence. The ecosystem page lists our partners; the working groups list our gaps. Find the gap shaped like you.</p>
<p>Someone has to keep the registers while others watch the skies. I have chosen the registers. The view from here is better than you would think — because every clean record is a small proof that Uganda can run the complex things, and orbit is nothing if not complex.</p>
<p><em>— Samuel Oumo, Uganda Astronomical Society</em></p>', 'article', '["ecosystem","institution-building","space-future"]', 'img/telescope-night-city.jpg', 'published', NULL, NOW(), NOW()
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM articles WHERE title = 'The Unglamorous Work of Reaching Orbit');
