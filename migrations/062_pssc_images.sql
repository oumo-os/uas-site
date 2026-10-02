-- Migration 062: repair PSSC programme images.
-- Replaces 5 dead pasted images (src="#" — data-URIs killed by the
-- sanitizer before the editor learned to upload pastes) and self-hosts
-- the Wow! signal hotlink. Deterministic full-description rewrite for
-- programme public-science-space-culture; safe to re-run (same content).

UPDATE programmes SET description = '<p dir="ltr">There is something rather strange about astronomy.</p>
<p dir="ltr">In a single evening, you can look at a planet through a small telescope, wonder what a distant galaxy looked like billions of years ago, or listen for a signal that may have crossed the cosmos to reach us. You can also sit with a story about a sky god told by people who watched these same stars long before anyone built a telescope.</p>
<p dir="ltr">Somehow, all of these belong to the same story: <strong>our relationship with the cosmos.</strong></p>
<p dir="ltr">That is the territory of Public Science &amp; Space Culture (PSSC), a programme of the Uganda Astronomical Society.</p>
<p dir="ltr"><strong><br><img src="img/pssc-milky-way-silhouette.jpg" alt="A lone stargazer beneath the Milky Way"></strong></p>
<p dir="ltr"><br>PSSC exists to make space part of public life, not only as a subject to be taught, but as something people can experience, question and take part in.</p>
<p dir="ltr">Space gets into people in different ways. For some it was the sight of the Moon, full and low over the horizon. For others it was the Apollo missions, or a favourite episode of Star Trek, or a game that let them fly somewhere they could never go. Some arrived through the search for life beyond Earth, others through the sky stories of their own ancestors. Any of these is a perfectly good way in.</p><p dir="ltr">You do not have to arrive as an astronomer. Curiosity is a perfectly good starting qualification.<br><br><strong><img src="img/pssc-observing-night.jpg" alt="Telescopes and stargazers under a starry sky"></strong></p>
<p dir="ltr"><br>Our relationship with the sky has never been purely scientific. Long before observatories, people watched the heavens to navigate, to mark the seasons, to keep calendars and to explain where they came from. Every culture named what it saw above it and gave it meaning.</p>
<p dir="ltr">Today we have spacecraft, orbiting observatories and radio telescopes. We have sent machines to other worlds, and placed messages aboard probes now leaving the Solar System. Yet the impulse behind all of it is remarkably old:</p>
<p dir="ltr"><strong>Look up. Ask questions. Try to understand.</strong></p>
<p dir="ltr"><strong><br><img src="img/pssc-allen-telescope-array.jpg" alt="The Allen Telescope Array"></strong></p>
<p dir="ltr"><br>PSSC makes room for many kinds of encounter with the sky:<br><br></p>
<p dir="ltr"><strong>Science:</strong> observation, evidence, discovery and research.</p>
<p dir="ltr"><strong>Exploration:</strong> missions, technologies and the growing human presence beyond Earth, and what past missions did to the people who watched them.</p>
<p dir="ltr"><strong>Participation:</strong> citizen science, observing campaigns, searches, experiments and collaborative projects.</p>
<p dir="ltr"><strong>Culture:</strong> history, mythology, art, literature, film, games and philosophy, the many ways societies have imagined their place among the stars.</p>
<p dir="ltr"><strong>The future:</strong> the questions we haven''t answered and the things we haven''t yet built.<br><br></p>
<p dir="ltr">These are not the same thing, and we will not pretend they are. A scientific observation is not a cultural tradition, and a fictional possibility is not an established fact. But they can share a conversation, and that conversation is what I hope this programme will create.<br><br></p><p dir="ltr"><img class="srjgSKje" width="512" height="341" alt="A satellite dish sitting in the middle of a field. Radio telescope astronomy radio antenna, science technology." src="https://cache.getarchive.net/Prod/thumb/cdn12/L3Bob3RvLzIwMTYvMTIvMzEvcmFkaW8tdGVsZXNjb3BlLWFzdHJvbm9teS1yYWRpby1hbnRlbm5hLXNjaWVuY2UtdGVjaG5vbG9neS00ZjhiNjktNjQwLmpwZw%3D%3D/512/341/webp"><br>I picture a place where:</p>
<ul dir="ltr"><li>someone arrives for an evening about the beauty of the Moon and leaves wanting to understand how it formed;</li><li>someone who has never touched a telescope joins an observing session;</li><li>a student discovers citizen science and goes on to contribute to real research;</li><li>a lover of science fiction or space games finds the real science behind the stories;</li><li>we ask whether we are alone, and what we would say if someone answered;</li><li>ancient sky traditions sit beside modern astronomy, not because they make the same claims, but because both reveal how people have looked upward and wondered.</li></ul>
<p dir="ltr"><strong><br></strong><img class="size-full wp-image-501407 webpexpress-processed" src="https://www.re-thinkingthefuture.com/wp-content/uploads/2024/10/A13273-Reaching-for-the-Moon-Space-Architecture-in-the-Artemis-Program-Image-2.jpg?w=999" alt="Reaching for the Moon Space Architecture in the Artemis Program-Sheet2" width="1600" height="900"><img src="img/pssc-wow-signal.jpg" alt="The Wow! signal printout"><br></p>
<p dir="ltr">Sometimes the most interesting question is not <em>what do we know?</em> It is <strong>what could we find out?</strong></p>
<p dir="ltr">So PSSC will support established activities, such as public talks, observing sessions, citizen-science campaigns, exhibitions and educational programmes, alongside new experiments in how space is shared and experienced. It will also be a public doorway into the wider work of the Uganda Astronomical Society, from student research and asteroid searches to projects exploring humanity''s future beyond Earth.</p>
<p dir="ltr">A confession: I am, unapologetically, a space science nerd, so expect plenty of science from me. But this programme is bigger than any one enthusiasm, and I hope it will hold many of yours.<br><br></p>
<p dir="ltr"><img src="img/pssc-moon-geology.jpg" alt="Annotated geology of the Moon"><img src="img/pssc-milky-way-hillside.jpg" alt="Milky Way over a moonlit hillside"><img src="img/pssc-moon-geology.jpg" alt="Annotated geology of the Moon"></p>
<p dir="ltr"><br>Ultimately, I don''t want PSSC to be a programme where people come to be told about space. I want them to <strong>do something with it.</strong></p>
<p dir="ltr">Look through the telescope.<br>
Ask the inconvenient question.<br>
Search the data.<br>
Challenge an assumption.<br>
Tell a story.<br>
Build something.<br>
Join a project.</p>
<p dir="ltr">Or simply stand beneath a clear Ugandan sky and wonder what is actually up there.</p>
<p dir="ltr">There is plenty of universe to go around.</p>
<p dir="ltr"><strong>Welcome to Public Science &amp; Space Culture.</strong></p>
<p dir="ltr"><br>
<em>Programme Lead, Public Science &amp; Space Culture</em><br>
<em>Uganda Astronomical Society</em></p><br>'
WHERE slug = 'public-science-space-culture';
