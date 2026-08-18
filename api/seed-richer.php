<?php
require __DIR__ . '/config.php';
$db = db();

// === ADDITIONAL EVENTS ===
// Schema: date (datetime), end_date (datetime), capacity (int), status, programme_id, organizer_id, created_by
$events = [
  ['Astrophotography Workshop','A hands-on workshop covering DSLR and smartphone astrophotography techniques. Participants will learn to capture the Milky Way, star trails, and lunar craters. Led by UAS outreach team.','2026-09-15 18:00:00','2026-09-15 21:00:00','Kololo Airstrip','published',25,1,NULL,8],
  ['Public Telescope Night — Entebbe','Bring the family for an evening of telescopic observing at the Entebbe Zoo grounds. Jupiter, Saturn, and the Orion Nebula will be visible.','2026-10-03 19:00:00','2026-10-03 22:00:00','Entebbe Zoo Grounds','published',80,1,NULL,12],
  ['UAS Annual General Meeting','Annual gathering of all UAS members. Agenda includes annual report, elections, and strategic planning for 2027.','2026-11-20 09:00:00','2026-11-20 16:00:00','Makerere University, Senate Hall','published',120,1,NULL,1],
  ['Deep Sky Observing Marathon','A night-long deep sky object observing marathon targeting Messier catalogue objects. Open to experienced observers.','2026-10-24 20:00:00','2026-10-25 06:00:00','Murchison Falls National Park','published',20,3,NULL,1],
  ['Kids Astronomy Day','Fun astronomy activities for children aged 6-14: solar viewing, planetarium show, constellation storytelling, and craft activities.','2026-12-14 10:00:00','2026-12-14 14:00:00','Ndere Cultural Centre','published',60,2,NULL,12],
  ['International Observe the Moon Night','Global event celebrating lunar observation. Join UAS at Kololo for guided moon viewing through telescopes.','2026-11-18 18:30:00','2026-11-18 21:30:00','Kololo Airstrip','published',40,1,NULL,7],
  ['Exoplanet Detection Workshop','Learn how professional and amateur astronomers detect planets orbiting distant stars using transit photometry.','2026-12-05 14:00:00','2026-12-05 17:00:00','Makerere University, Physics Dept','published',30,2,NULL,3],
  ['Galaxy Season Star Party','A multi-night observing event targeting galaxies in the Virgo Cluster. Camping available on site.','2027-03-14 18:00:00','2027-03-15 06:00:00','Lake Mburo National Park','published',25,3,NULL,1],
];

$stmt = $db->prepare('INSERT INTO events (title, description, date, end_date, location, status, capacity, programme_id, organizer_id, created_by, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,NOW(),NOW())');
foreach ($events as $e) {
  $stmt->execute($e);
  echo "Event: {$e[0]}\n";
}

// === ADDITIONAL ARTICLES ===
// [title, category, body, image_url, author_id]
$articles = [
  ['Understanding Redshift: A Guide for Amateur Astronomers','educational','<p>Redshift is one of the most important concepts in astronomy. It describes how light from distant objects is stretched to longer (redder) wavelengths as the universe expands.</p><p>For amateur astronomers, understanding redshift helps contextualize the objects you observe. When you look at a galaxy through your telescope, its light may have traveled billions of years to reach you, stretched by the expansion of space along the way.</p><h3>Types of Redshift</h3><p>There are three main types: cosmological (due to universe expansion), Doppler (due to relative motion), and gravitational (due to strong gravity fields near massive objects).</p><p>The Hubble-Lemaître Law relates a galaxy\'s redshift to its distance, forming the foundation of our understanding of the expanding universe.</p>','astronomy-redshift.jpg',1],
  ['Building a DIY Solar Filter for Safe Sun Observation','educational','<p>Solar observation requires proper filtration to protect your eyes and equipment. A Baader film solar filter is the safest and most affordable option for telescopic solar viewing.</p><p>This guide walks you through constructing a cell-mounted solar filter for popular telescope apertures. You will need Baader AstroSolar film (ND 5.0), a cardboard or wooden cell, and basic crafting tools.</p><h3>Materials</h3><p>Baader AstroSolar Safety Film (OD 5.0), 1-2mm thick cardboard or foam board, black acrylic paint, scissors, ruler, and adhesive.</p><h3>Steps</h3><p>Measure your telescope aperture. Cut concentric rings to create a friction-fit cell. Paint the interior matte black. Secure the solar film over the aperture using retaining rings. The filter must fit snugly and cannot fall off during use.</p><p>Always use the full-aperture filter at the front of the telescope. Never use eyepiece-mounted filters for solar observation — they can crack from heat.</p>','solar-filter.jpg',2],
  ['Uganda Dark Sky Sites: A Comprehensive Guide','observing_report','<p>Uganda hosts several excellent dark sky locations ideal for astronomical observation. This guide catalogs the best sites based on light pollution measurements and accessibility.</p><h3>Tier 1 — Pristine Skies</h3><p><strong>Murchison Falls National Park</strong>: Bortle 2 skies. The park offers expansive horizons and minimal artificial lighting. Best visited during dry seasons (December-February, June-September).</p><p><strong>Lake Mburo National Park</strong>: Bortle 2-3. Closer to Kampala than Murchison, with good road access and camping facilities.</p><h3>Tier 2 — Good Skies</h3><p><strong>Mount Elgon National Park</strong>: Bortle 3. Higher altitude provides thinner atmosphere and better seeing conditions. Some light pollution from nearby towns.</p><p><strong>Queen Elizabeth National Park</strong>: Bortle 3-4. Good for wide-field observation but Kasenyi tourism area has some light pollution.</p><h3>Urban Observing</h3><p>Within Kampala, Kololo Airstrip remains the best urban observing site due to its open space and distance from heavy commercial lighting.</p>','dark-sites.jpg',3],
  ['The Physics of Black Holes: What We Know in 2026','educational','<p>Black holes remain among the most fascinating objects in the universe. Here is our current understanding as of 2026.</p><h3>Formation</h3><p>Stellar-mass black holes form from the gravitational collapse of massive stars (typically >25 solar masses) at the end of their lives. Supermassive black holes (millions to billions of solar masses) sit at the centers of galaxies, including our own Milky Way\'s Sagittarius A*.</p><h3>Observation</h3><p>While black holes themselves emit no light, we detect them through their effects: accretion disks of heated material, gravitational lensing of background stars, gravitational waves from mergers, and the motion of nearby stars.</p><p>The Event Horizon Telescope collaboration continues to image black hole shadows, with increasing resolution revealing structure in the accretion flow around M87* and Sgr A*.</p><h3>Information Paradox</h3><p>The black hole information paradox remains an active area of research. Recent theoretical work suggests information may be encoded in Hawking radiation or stored in a remnant at the horizon.</p>','blackholes.jpg',1],
  ['Report: Kampala International School Telescope Installation','project_report','<p>UAS completed the installation and commissioning of a 12-inch Dobsonian telescope at Kampala International School on 15 August 2026. This is the third installation under the School Telescope Programme.</p><h3>Installation Details</h3><p>Equipment: Sky-Watcher Dobsonian 12" Collapsible, two eyepieces (25mm, 10mm), Telrad finder, and a basic star atlas. The school provided storage space and a commitment to regular usage.</p><h3>Training</h3><p>A two-hour training session covered telescope assembly, collimation, basic star-hopping, and safety protocols. Twelve students and two teachers participated.</p><h3>Impact</h3><p>The telescope has already been used for three after-school observation sessions. Students reported seeing Jupiter\'s moons, Saturn\'s rings, and the Orion Nebula for the first time.</p><p>UAS will follow up quarterly and provide ongoing support to the school\'s astronomy club.</p>','kis-telescope.jpg',4],
  ['Announcement: New Partnership with Uganda Wildlife Authority','announcement','<p>Uganda Astronomical Society is pleased to announce a formal partnership with the Uganda Wildlife Authority (UWA) to establish astronomy tourism programs in national parks.</p><p>This partnership will enable:</p><ul><li>Regular star-gazing events at Murchison Falls, Lake Mburo, and Queen Elizabeth National Parks</li><li>Training for UWA rangers as astronomy guides</li><li>Development of astronomy tourism marketing materials</li><li>Research collaboration on light pollution monitoring in protected areas</li></ul><p>The first joint event is planned for October 2026 at Lake Mburo National Park. Details will be shared on our events page.</p>','uwa-partnership.jpg',2],
  ['Monthly Sky Guide: September 2026','announcement','<p>Here is what to look for in the night sky this September from Uganda.</p><h3>Planets</h3><p><strong>Jupiter</strong> rises around 22:00 and is excellent for observation in the early morning hours. Look for the four Galilean moons.</p><p><strong>Saturn</strong> is visible all night, with its rings tilted at a favorable angle. A 4-inch telescope reveals the Cassini division.</p><p><strong>Mars</strong> is currently on the far side of the Sun and not well placed for observation.</p><h3>Moon Phases</h3><p>New Moon: September 1 | First Quarter: September 8 | Full Moon: September 15 | Last Quarter: September 22</p><h3>Highlights</h3><p>The September equinox falls on September 22. Day and night will be approximately equal in length.</p><p>The Autumnal Equinox is a good time to observe the Milky Way stretching across the sky before moonrise.</p>','sky-guide.jpg',1],
];

$stmt = $db->prepare('INSERT INTO articles (author_id, title, body, category, image_url, status, published_at, created_at, updated_at) VALUES (?,?,?,?,?,?,NOW(),NOW(),NOW())');
foreach ($articles as $a) {
  // $a: [title, category, body, image_url, author_id]
  $stmt->execute([$a[4], $a[0], $a[2], $a[1], $a[3], 'published']);
  echo "Article: {$a[0]}\n";
}

// === ADDITIONAL FINANCIAL RECORDS (2026) ===
// Schema: type, amount, category (enum), programme_id, project_id, event_id, budget_item_id, description, recorded_by, record_date, due_date, status, notes
$records = [
  ['income','50000000','grant',1,NULL,NULL,NULL,'Grant from National Science Foundation',1,'2026-02-15','2026-02-15','approved','NSF research grant for 2026 programmes'],
  ['income','15000000','sponsorship',1,NULL,NULL,NULL,'Corporate Sponsorship — MTN Uganda',2,'2026-03-01','2026-03-01','approved','Annual corporate sponsorship'],
  ['income','3200000','membership',1,NULL,NULL,NULL,'Member Registration Fees (Q1)',1,'2026-03-31','2026-03-31','approved','16 new members × UGX 200,000'],
  ['income','2400000','event',1,NULL,1,NULL,'Event Ticket Sales — Stargazing Night',1,'2026-08-20','2026-08-20','approved','80 attendees × UGX 30,000'],
  ['income','1500000','training',2,NULL,3,NULL,'Telescope Workshop Fees',3,'2026-09-16','2026-09-16','approved','25 participants × UGX 60,000'],
  ['expense','8500000','equipment',1,NULL,NULL,NULL,'Equipment Purchase — Dobsonian 12" (KIS)',4,'2026-08-15','2026-08-15','paid','Third school telescope installation'],
  ['expense','6000000','administration',1,NULL,NULL,NULL,'Staff Salaries (Aug 2026)',1,'2026-08-31','2026-08-31','paid','Monthly salaries'],
  ['expense','850000','other',1,NULL,NULL,NULL,'Internet & Software Licenses',1,'2026-09-01','2026-09-01','paid','Monthly recurring'],
  ['expense','3200000','travel',3,NULL,NULL,NULL,'Murchison Field Trip Transport',4,'2026-10-20','2026-10-20','approved','Bus hire and fuel for 25 observers'],
  ['expense','1200000','publication',3,NULL,NULL,NULL,'Printing — Star Atlases & Pamphlets',2,'2026-11-01','2026-11-01','approved','Educational materials for outreach'],
  ['payable','2000000','event',1,NULL,NULL,NULL,'Venue Rental — Makerere Hall',1,'2026-11-20','2026-11-20','approved','AGM venue booking'],
  ['receivable','30000000','grant',1,NULL,NULL,NULL,'Pending Grant from UNESCO',1,'2026-12-31','2026-12-31','pending','UNESCO astronomy education grant application'],
  ['receivable','5000000','sponsorship',1,NULL,NULL,NULL,'Sponsorship — UWA Events',1,'2026-10-15','2026-10-15','pending','Joint astronomy tourism events'],
];

$stmt = $db->prepare('INSERT INTO financial_records (type, amount, category, programme_id, project_id, event_id, budget_item_id, description, recorded_by, record_date, due_date, status, notes, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())');
foreach ($records as $r) {
  $stmt->execute($r);
  echo "Record: {$r[7]}\n";
}

echo "\nDone — seeded additional events, articles, and financial records.\n";
