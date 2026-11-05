"""
AushadhSeva — SEVA First Innovation Challenge 2026
Jury Presentation Generator (.pptx)
Widescreen 16:9 Presentation with Professional Clinical Design & Speaker Notes
"""

import sys
import os
import pptx
from pptx.util import Inches, Pt
from pptx.dml.color import RGBColor
from pptx.enum.text import PP_ALIGN, MSO_ANCHOR
from pptx.enum.shapes import MSO_SHAPE

def build_presentation(output_path):
    prs = pptx.Presentation()
    prs.slide_width = Inches(13.333)
    prs.slide_height = Inches(7.5)
    blank_layout = prs.slide_layouts[6]

    # Theme Colors
    DARK_NAVY = RGBColor(7, 14, 27)      # #070e1b
    SLATE_DARK = RGBColor(15, 23, 42)    # #0f172a
    MED_BLUE = RGBColor(2, 132, 199)     # #0284c7
    SKY_BLUE = RGBColor(56, 189, 248)    # #38bdf8
    WHITE = RGBColor(255, 255, 255)
    CARD_BG = RGBColor(248, 250, 252)    # #f8fafc
    CARD_BORDER = RGBColor(226, 232, 240)# #e2e8f0
    MUTED_TEXT = RGBColor(100, 116, 139) # #64748b
    CRIMSON = RGBColor(220, 38, 38)      # #dc2626
    EMERALD = RGBColor(22, 163, 74)      # #16a34a
    AMBER = RGBColor(234, 88, 12)        # #ea580c

    def set_slide_background(slide, color):
        bg = slide.background
        fill = bg.fill
        fill.solid()
        fill.fore_color.rgb = color

    def add_header(slide, title, category="SEVA FIRST INNOVATION CHALLENGE 2026 • THEME 3", slide_num=None):
        # Top accent bar
        bar = slide.shapes.add_shape(MSO_SHAPE.RECTANGLE, Inches(0), Inches(0), Inches(13.333), Inches(0.08))
        bar.fill.solid()
        bar.fill.fore_color.rgb = MED_BLUE
        bar.line.fill.background()

        # Category Tracker
        cat_box = slide.shapes.add_textbox(Inches(0.8), Inches(0.35), Inches(10), Inches(0.3))
        tf_cat = cat_box.text_frame
        tf_cat.word_wrap = True
        p_cat = tf_cat.paragraphs[0]
        p_cat.text = category.upper()
        p_cat.font.name = 'Arial'
        p_cat.font.size = Pt(10)
        p_cat.font.bold = True
        p_cat.font.color.rgb = MED_BLUE

        # Title
        title_box = slide.shapes.add_textbox(Inches(0.8), Inches(0.65), Inches(11), Inches(0.6))
        tf_title = title_box.text_frame
        tf_title.word_wrap = True
        p_title = tf_title.paragraphs[0]
        p_title.text = title
        p_title.font.name = 'Arial'
        p_title.font.size = Pt(24)
        p_title.font.bold = True
        p_title.font.color.rgb = SLATE_DARK

        # Slide number
        if slide_num:
            num_box = slide.shapes.add_textbox(Inches(12.0), Inches(0.4), Inches(0.8), Inches(0.4))
            p_num = num_box.text_frame.paragraphs[0]
            p_num.text = f"{slide_num:02d} / 13"
            p_num.font.name = 'Arial'
            p_num.font.size = Pt(11)
            p_num.font.bold = True
            p_num.font.color.rgb = MUTED_TEXT
            p_num.alignment = PP_ALIGN.RIGHT

    def add_card(slide, left, top, width, height, bg_color=CARD_BG, border_color=CARD_BORDER):
        card = slide.shapes.add_shape(MSO_SHAPE.ROUNDED_RECTANGLE, left, top, width, height)
        card.fill.solid()
        card.fill.fore_color.rgb = bg_color
        card.line.color.rgb = border_color
        card.line.width = Pt(1.5)
        return card

    def add_stat_box(slide, left, top, width, height, value, label, subtext="", val_color=MED_BLUE):
        add_card(slide, left, top, width, height)
        box = slide.shapes.add_textbox(left + Inches(0.15), top + Inches(0.15), width - Inches(0.3), height - Inches(0.3))
        tf = box.text_frame
        tf.word_wrap = True
        
        p_val = tf.paragraphs[0]
        p_val.text = str(value)
        p_val.font.name = 'Arial'
        p_val.font.size = Pt(32)
        p_val.font.bold = True
        p_val.font.color.rgb = val_color

        p_lbl = tf.add_paragraph()
        p_lbl.text = label
        p_lbl.font.name = 'Arial'
        p_lbl.font.size = Pt(13)
        p_lbl.font.bold = True
        p_lbl.font.color.rgb = SLATE_DARK
        p_lbl.space_before = Pt(4)

        if subtext:
            p_sub = tf.add_paragraph()
            p_sub.text = subtext
            p_sub.font.name = 'Arial'
            p_sub.font.size = Pt(10.5)
            p_sub.font.color.rgb = MUTED_TEXT
            p_sub.space_before = Pt(2)

    def set_notes(slide, notes_text):
        notes_slide = slide.notes_slide
        tf = notes_slide.notes_text_frame
        tf.text = notes_text

    # =========================================================================
    # SLIDE 1: TITLE SLIDE (Dark Navy Theme)
    # =========================================================================
    s1 = prs.slides.add_slide(blank_layout)
    set_slide_background(s1, DARK_NAVY)

    # Accent glow badge
    b1 = s1.shapes.add_shape(MSO_SHAPE.ROUNDED_RECTANGLE, Inches(0.8), Inches(1.2), Inches(3.8), Inches(0.45))
    b1.fill.solid()
    b1.fill.fore_color.rgb = RGBColor(3, 105, 161)
    b1.line.color.rgb = SKY_BLUE
    p_b1 = b1.text_frame.paragraphs[0]
    p_b1.text = "SEVA FIRST INNOVATION CHALLENGE 2026"
    p_b1.font.name = 'Arial'
    p_b1.font.size = Pt(11)
    p_b1.font.bold = True
    p_b1.font.color.rgb = WHITE
    p_b1.alignment = PP_ALIGN.CENTER

    # Main Brand Title
    tb_title = s1.shapes.add_textbox(Inches(0.8), Inches(1.8), Inches(11.5), Inches(1.8))
    tf1 = tb_title.text_frame
    p1 = tf1.paragraphs[0]
    p1.text = "AushadhSeva (औषध सेवा)"
    p1.font.name = 'Arial'
    p1.font.size = Pt(50)
    p1.font.bold = True
    p1.font.color.rgb = WHITE

    p2 = tf1.add_paragraph()
    p2.text = "Inclusive Medication Safety & Clinical Decision Support System"
    p2.font.name = 'Arial'
    p2.font.size = Pt(22)
    p2.font.bold = True
    p2.font.color.rgb = SKY_BLUE
    p2.space_before = Pt(8)

    # Subtitle details
    tb_desc = s1.shapes.add_textbox(Inches(0.8), Inches(3.8), Inches(11.5), Inches(1.2))
    tf_desc = tb_desc.text_frame
    tf_desc.word_wrap = True
    p3 = tf_desc.paragraphs[0]
    p3.text = "A deterministic, zero-hallucination 'second pair of eyes' that reconciles multi-doctor longitudinal prescriptions, resolves Indian brand formulations to canonical generics, and prevents life-threatening conflicts across 8 Indian languages."
    p3.font.name = 'Arial'
    p3.font.size = Pt(15)
    p3.font.color.rgb = RGBColor(203, 213, 225)

    # Footer Card Info
    c1 = s1.shapes.add_shape(MSO_SHAPE.RECTANGLE, Inches(0.8), Inches(5.6), Inches(11.733), Inches(1.1))
    c1.fill.solid()
    c1.fill.fore_color.rgb = RGBColor(15, 23, 42)
    c1.line.color.rgb = RGBColor(51, 65, 85)
    tf_c1 = c1.text_frame
    p_meta = tf_c1.paragraphs[0]
    p_meta.text = "Healthcare Problem H4: Medication Safety Second Pair of Eyes  |  Theme 3: Swasth & Samavesh Bharat"
    p_meta.font.name = 'Arial'
    p_meta.font.size = Pt(13)
    p_meta.font.bold = True
    p_meta.font.color.rgb = SKY_BLUE

    p_team = tf_c1.add_paragraph()
    p_team.text = "Developed by Team Arrow (Dev02005)  •  Live Demo: http://localhost:5000  •  GitHub: Dev02005/TEAM-ARROW"
    p_team.font.name = 'Arial'
    p_team.font.size = Pt(12)
    p_team.font.color.rgb = WHITE
    p_team.space_before = Pt(4)

    set_notes(s1, "Jury Opening: Good morning respected jury members. We are Team Arrow presenting AushadhSeva for Healthcare Problem H4. In Indian district hospitals, elderly patients consult up to three distinct doctors over 2+ years. Today, we show you an inclusive, deterministic platform that catches 90% of serious drug conflicts while communicating in 8 Indian languages with native voice synthesis.")

    # =========================================================================
    # SLIDE 2: THE PROBLEM (Ground Reality)
    # =========================================================================
    s2 = prs.slides.add_slide(blank_layout)
    add_header(s2, "The Problem: Polypharmacy & Alert Fatigue in Public Hospitals", slide_num=2)

    # 3 Column Cards
    col_w = Inches(3.7)
    gap = Inches(0.3)
    left_base = Inches(0.8)
    top_pos = Inches(1.4)
    card_h = Inches(5.4)

    # Card 1: Fragmentation
    add_card(s2, left_base, top_pos, col_w, card_h)
    tb = s2.shapes.add_textbox(left_base + Inches(0.2), top_pos + Inches(0.2), col_w - Inches(0.4), card_h - Inches(0.4))
    tf = tb.text_frame
    tf.word_wrap = True
    p = tf.paragraphs[0]
    p.text = "1. Multi-Doctor Fragmentation"
    p.font.size = Pt(16); p.font.bold = True; p.font.color.rgb = SLATE_DARK
    
    bullets1 = [
        "Elderly polypharmacy patients consult 3+ doctors: PHC physician, District Cardiologist, and Private Practitioner.",
        "Prescriptions span 2+ years without a unified electronic record.",
        "Neither doctor knows what the other prescribed.",
        "Uncoordinated additions lead to unintended duplicate therapies and severe drug interactions."
    ]
    for b in bullets1:
        p_b = tf.add_paragraph()
        p_b.text = f"• {b}"
        p_b.font.size = Pt(12.5); p_b.font.color.rgb = MUTED_TEXT; p_b.space_before = Pt(8)

    # Card 2: Messy Transcriptions
    left2 = left_base + col_w + gap
    add_card(s2, left2, top_pos, col_w, card_h)
    tb = s2.shapes.add_textbox(left2 + Inches(0.2), top_pos + Inches(0.2), col_w - Inches(0.4), card_h - Inches(0.4))
    tf = tb.text_frame
    tf.word_wrap = True
    p = tf.paragraphs[0]
    p.text = "2. Nurse Transcription Chaos"
    p.font.size = Pt(16); p.font.bold = True; p.font.color.rgb = SLATE_DARK
    
    bullets2 = [
        "Ward nurses transcribe handwritten slips, old blister foils, and faded bottles.",
        "Indian Combination Brands hide active generics: Glycomet-GP hides both glimepiride AND metformin.",
        "Frequent Misspellings: 'croken' for paracetamol, 'pntp' for pantoprazole, 'aspyrin' for aspirin.",
        "Dosage Noise: '1-0-1', 'BD', 'Tab', mixed with non-drug lines like 'BP check' and 'Glucometer strips'."
    ]
    for b in bullets2:
        p_b = tf.add_paragraph()
        p_b.text = f"• {b}"
        p_b.font.size = Pt(12.5); p_b.font.color.rgb = MUTED_TEXT; p_b.space_before = Pt(8)

    # Card 3: Alert Fatigue
    left3 = left2 + col_w + gap
    add_card(s2, left3, top_pos, col_w, card_h, border_color=CRIMSON)
    tb = s2.shapes.add_textbox(left3 + Inches(0.2), top_pos + Inches(0.2), col_w - Inches(0.4), card_h - Inches(0.4))
    tf = tb.text_frame
    tf.word_wrap = True
    p = tf.paragraphs[0]
    p.text = "3. The Danger of Alert Fatigue"
    p.font.size = Pt(16); p.font.bold = True; p.font.color.rgb = CRIMSON
    
    bullets3 = [
        "Commercial CDS tools cry wolf: hundreds of trivial alarms cause doctors to override 90%+ of alerts.",
        "Silent Renal Overdoses: Low eGFR cutoffs (KDIGO) are missed, causing metformin-induced lactic acidosis.",
        "Zero Indian Inclusivity: Existing tools work in complex English medical jargon, leaving patients and elder citizens completely in the dark."
    ]
    for b in bullets3:
        p_b = tf.add_paragraph()
        p_b.text = f"• {b}"
        p_b.font.size = Pt(12.5); p_b.font.color.rgb = MUTED_TEXT; p_b.space_before = Pt(8)

    set_notes(s2, "Speaker Notes: Highlight Chandrapuri hospital context. When Eleanor enters the ward, nurses transcribe 'Glycomet-GP 1' and 'Crocin'. Glycomet-GP has 2 active ingredients. If another doctor prescribes plain Metformin, the patient suffers fatal lactic acidosis because the brand hidden ingredients were never expanded. And doctors ignore alerts when systems cry wolf.")

    # =========================================================================
    # SLIDE 3: THE SOLUTION & ARCHITECTURE
    # =========================================================================
    s3 = prs.slides.add_slide(blank_layout)
    add_header(s3, "The Solution: AushadhSeva System Architecture", slide_num=3)

    # Left: High Level Value Prop
    add_card(s3, Inches(0.8), Inches(1.4), Inches(4.5), Inches(5.4))
    tb = s3.shapes.add_textbox(Inches(1.0), Inches(1.6), Inches(4.1), Inches(5.0))
    tf = tb.text_frame
    tf.word_wrap = True
    p = tf.paragraphs[0]
    p.text = "Three Core Pillars"
    p.font.size = Pt(18); p.font.bold = True; p.font.color.rgb = MED_BLUE

    points = [
        ("1. Intelligent Token Resolver", "Normalizes dirty brand names, strips dosage noise, and unpacks combination formulations with >96.4% precision."),
        ("2. Deterministic H4 Engine", "100% reproducible, zero-hallucination pairwise safety checks against authoritative clinical datasets in <8 milliseconds."),
        ("3. Samavesh (Inclusion)", "Native speech synthesis and UI localization across 8 Indian languages, elder-friendly typography, and PMBJP Jan Aushadhi generic savings.")
    ]
    for heading, desc in points:
        p_h = tf.add_paragraph()
        p_h.text = heading
        p_h.font.size = Pt(14); p_h.font.bold = True; p_h.font.color.rgb = SLATE_DARK; p_h.space_before = Pt(14)
        p_d = tf.add_paragraph()
        p_d.text = desc
        p_d.font.size = Pt(12); p_d.font.color.rgb = MUTED_TEXT; p_d.space_before = Pt(2)

    # Right: Architecture Flow
    add_card(s3, Inches(5.6), Inches(1.4), Inches(6.9), Inches(5.4))
    tb_arch = s3.shapes.add_textbox(Inches(5.8), Inches(1.6), Inches(6.5), Inches(5.0))
    tf_arch = tb_arch.text_frame
    tf_arch.word_wrap = True
    
    p = tf_arch.paragraphs[0]
    p.text = "System Data Flow Pipeline"
    p.font.size = Pt(18); p.font.bold = True; p.font.color.rgb = SLATE_DARK

    arch_steps = [
        ("Step 1: Input Ingestion", "Doctor handwriting transcription, camera photo of blister strip/slip, or voice-assisted citizen entry."),
        ("Step 2: Tokenization & Resolver", "Filters tasks ('BP check'), strips frequencies ('1-0-1'), expands combos ('Glycomet-GP' -> glimepiride + metformin)."),
        ("Step 3: Deterministic Rule Matrix", "Evaluates active regimen + candidate drug across 2,196 interactions, 169 renal rules, and duplicate therapy groups."),
        ("Step 4: Clinical Explanations & Staging", "Maps KDIGO kidney stage (G1-G5), formats actionable clinical advice, and calculates safety score %."),
        ("Step 5: Multilingual Delivery & Audio", "Translates into patient's language (EN, TE, HI, TA, ML, KN, OR, UR) with native voice readout.")
    ]
    for s_title, s_desc in arch_steps:
        p_st = tf_arch.add_paragraph()
        p_st.text = f"▶ {s_title}"
        p_st.font.size = Pt(13); p_st.font.bold = True; p_st.font.color.rgb = MED_BLUE; p_st.space_before = Pt(10)
        p_sd = tf_arch.add_paragraph()
        p_sd.text = s_desc
        p_sd.font.size = Pt(11.5); p_sd.font.color.rgb = MUTED_TEXT; p_sd.space_before = Pt(2)

    set_notes(s3, "Speaker Notes: Explain why we chose a deterministic rule engine over pure generative LLMs: in clinical pharmacology, hallucination is fatal. A deterministic pipeline guarantees 100% reproducible decisions with zero hallucinations and executes 3,500 patients in 8 seconds.")

    # =========================================================================
    # SLIDE 4: THE TOKEN RESOLVER (NLP & Brand Mapping)
    # =========================================================================
    s4 = prs.slides.add_slide(blank_layout)
    add_header(s4, "Intelligent Token Resolver: Solving Indian Brand & Typo Challenges", slide_num=4)

    # 4 Stat / Feature Cards
    add_stat_box(s4, Inches(0.8), Inches(1.4), Inches(2.7), Inches(2.2), "96.48%", "Resolution Accuracy", "On messy district hospital prescriptions", val_color=EMERALD)
    add_stat_box(s4, Inches(3.8), Inches(1.4), Inches(2.7), Inches(2.2), "82.67%", "Misspelled Accuracy", "Baseline achieved 0.0% on typos", val_color=MED_BLUE)
    add_stat_box(s4, Inches(6.8), Inches(1.4), Inches(2.7), Inches(2.2), "98", "Combination Brands", "Unpacks hidden multi-generics", val_color=AMBER)
    add_stat_box(s4, Inches(9.8), Inches(1.4), Inches(2.7), Inches(2.2), "< 2 ms", "Resolver Latency", "Instant sub-second token lookup", val_color=EMERALD)

    # Detail Table of Examples
    add_card(s4, Inches(0.8), Inches(3.9), Inches(11.733), Inches(2.9))
    tb_ex = s4.shapes.add_textbox(Inches(1.0), Inches(4.0), Inches(11.3), Inches(2.7))
    tf_ex = tb_ex.text_frame
    tf_ex.word_wrap = True

    p = tf_ex.paragraphs[0]
    p.text = "Real-World District Hospital Normalization Examples"
    p.font.size = Pt(16); p.font.bold = True; p.font.color.rgb = SLATE_DARK

    examples = [
        ("Raw Input: 'T. Glycomet-GP 1 1-0-1 after food'", "Resolved To: glimepiride + metformin", "Action: Expanded multi-ingredient combination brand into 2 distinct INN generics."),
        ("Raw Input: 'croken 650 SOS for fever'", "Resolved To: paracetamol", "Action: Corrected severe spelling truncation via phonetic & edit distance."),
        ("Raw Input: 'Tab Pantodac 20mg OD before breakfast'", "Resolved To: pantoprazole", "Action: Stripped brand, dosage units, frequency, and food instruction."),
        ("Raw Input: 'Glucometer strips twice a week & BP charting'", "Resolved To: [EXCLUDED / NON-DRUG]", "Action: Filtered non-medicinal hospital tasks to prevent false clinical alarms.")
    ]
    for raw, res, act in examples:
        p_r = tf_ex.add_paragraph()
        p_r.text = f"{raw}  ➜  {res}"
        p_r.font.size = Pt(12); p_r.font.bold = True; p_r.font.color.rgb = MED_BLUE; p_r.space_before = Pt(6)
        p_a = tf_ex.add_paragraph()
        p_a.text = f"   {act}"
        p_a.font.size = Pt(11); p_a.font.color.rgb = MUTED_TEXT; p_a.space_before = Pt(1)

    set_notes(s4, "Speaker Notes: Point to the table. In Western datasets, Lipitor is Atorvastatin. But in India, Glycomet-GP 1 contains two drugs: Glimepiride and Metformin. Our resolver expands this deterministically. And look at 'croken' — our fuzzy matcher achieves 82.67% accuracy on misspelled entries where the challenge baseline scored exactly 0%.")

    # =========================================================================
    # SLIDE 5: DETERMINISTIC DECISION ENGINE (H4 Dimensions)
    # =========================================================================
    s5 = prs.slides.add_slide(blank_layout)
    add_header(s5, "Clinical Decision Engine: 4 Strict Safety Dimensions", slide_num=5)

    # 4 Quadrants
    qw = Inches(5.7)
    qh = Inches(2.55)
    
    # Q1: DDIs
    add_card(s5, Inches(0.8), Inches(1.4), qw, qh)
    tb = s5.shapes.add_textbox(Inches(1.0), Inches(1.5), qw - Inches(0.4), qh - Inches(0.2))
    tf = tb.text_frame; tf.word_wrap = True
    tf.paragraphs[0].text = "1. Drug-Drug Interactions (DDI)"
    tf.paragraphs[0].font.size = Pt(15); tf.paragraphs[0].font.bold = True; tf.paragraphs[0].font.color.rgb = CRIMSON
    p = tf.add_paragraph()
    p.text = "• 2,196 verified pairwise interaction rules\n• 4 Graded Severities: Contraindicated, Major, Moderate, Minor\n• High-Value Warning: Warfarin + Aspirin flagged as Contraindicated (fatal intracranial/GI bleed risk)."
    p.font.size = Pt(11.5); p.font.color.rgb = MUTED_TEXT; p.space_before = Pt(4)

    # Q2: Duplicate Therapy
    add_card(s5, Inches(6.8), Inches(1.4), qw, qh)
    tb = s5.shapes.add_textbox(Inches(7.0), Inches(1.5), qw - Inches(0.4), qh - Inches(0.2))
    tf = tb.text_frame; tf.word_wrap = True
    tf.paragraphs[0].text = "2. Unintended Duplicate Therapy"
    tf.paragraphs[0].font.size = Pt(15); tf.paragraphs[0].font.bold = True; tf.paragraphs[0].font.color.rgb = AMBER
    p = tf.add_paragraph()
    p.text = "• 74 Pharmacological Classes evaluated\n• Catches overlapping single + combination brands\n• Strict Rule: Paracetamol duplication is strictly graded MAJOR due to acute hepatotoxicity threshold limits."
    p.font.size = Pt(11.5); p.font.color.rgb = MUTED_TEXT; p.space_before = Pt(4)

    # Q3: KDIGO Renal
    add_card(s5, Inches(0.8), Inches(4.2), qw, qh)
    tb = s5.shapes.add_textbox(Inches(1.0), Inches(4.3), qw - Inches(0.4), qh - Inches(0.2))
    tf = tb.text_frame; tf.word_wrap = True
    tf.paragraphs[0].text = "3. KDIGO Renal Dosing (eGFR Staging)"
    tf.paragraphs[0].font.size = Pt(15); tf.paragraphs[0].font.bold = True; tf.paragraphs[0].font.color.rgb = MED_BLUE
    p = tf.add_paragraph()
    p.text = "• 169 renal dose adjustment tiers across 136 medicines\n• Stages patients into KDIGO G1 to G5 kidney failure\n• Anti-Fatigue Rule: Missing eGFR correctly suppresses renal alerts to prevent false alarm overload."
    p.font.size = Pt(11.5); p.font.color.rgb = MUTED_TEXT; p.space_before = Pt(4)

    # Q4: Contraindications & "Never Say Safe"
    add_card(s5, Inches(6.8), Inches(4.2), qw, qh)
    tb = s5.shapes.add_textbox(Inches(7.0), Inches(4.3), qw - Inches(0.4), qh - Inches(0.2))
    tf = tb.text_frame; tf.word_wrap = True
    tf.paragraphs[0].text = "4. Contraindications & 'Never Say Safe'"
    tf.paragraphs[0].font.size = Pt(15); tf.paragraphs[0].font.bold = True; tf.paragraphs[0].font.color.rgb = EMERALD
    p = tf.add_paragraph()
    p.text = "• Diagnosis Negation: 'no h/o Asthma' suppresses false contraindications\n• FDA Category X & D Teratogens detected\n• Unknown Drug Rule: Unrecognized medicines never report 'Safe'—system explicitly warns safety parameters are unverified."
    p.font.size = Pt(11.5); p.font.color.rgb = MUTED_TEXT; p.space_before = Pt(4)

    set_notes(s5, "Speaker Notes: Walk the jury through the 4 quadrants. Stress the 'Never Say Safe' principle. In typical apps, if a patient types an unlisted herbal powder, the app says '0 alerts found - Safe!'. That is clinically dangerous. In AushadhSeva, unverified medicines explicitly report: 'We cannot decide whether it is safe or not.'")

    # =========================================================================
    # SLIDE 6: PAN-INDIAN INCLUSIVITY (8 Languages & Audio)
    # =========================================================================
    s6 = prs.slides.add_slide(blank_layout)
    add_header(s6, "Pan-Indian Inclusivity: 8 Languages & Native Voice Readout", slide_num=6)

    # Left: 8 Languages Grid
    add_card(s6, Inches(0.8), Inches(1.4), Inches(5.7), Inches(5.4))
    tb = s6.shapes.add_textbox(Inches(1.0), Inches(1.6), Inches(5.3), Inches(5.0))
    tf = tb.text_frame; tf.word_wrap = True
    p = tf.paragraphs[0]
    p.text = "8 Constitutional Languages (364 Keys Each)"
    p.font.size = Pt(17); p.font.bold = True; p.font.color.rgb = MED_BLUE

    langs_info = [
        ("English", "en-IN", "Universal clinical & administrative baseline"),
        ("Telugu (తెలుగు)", "te-IN", "Complete Andhra & Telangana vernacular translation"),
        ("Hindi (हिन्दी)", "hi-IN", "Central & Northern Indian public health coverage"),
        ("Tamil (தமிழ்)", "ta-IN", "Tamil Nadu & Puducherry regional localization"),
        ("Malayalam (മലയാളം)", "ml-IN", "Kerala public health system alignment"),
        ("Kannada (ಕನ್ನಡ)", "kn-IN", "Karnataka state health coverage"),
        ("Odia (ଓଡ଼ିଆ)", "or-IN", "Odisha rural and district health network"),
        ("Urdu (اردو)", "ur-IN", "Full Right-To-Left (RTL) mirroring layout")
    ]
    for l_name, code, desc in langs_info:
        p_l = tf.add_paragraph()
        p_l.text = f"✔ {l_name} [{code}]"
        p_l.font.size = Pt(12); p_l.font.bold = True; p_l.font.color.rgb = SLATE_DARK; p_l.space_before = Pt(6)
        p_ld = tf.add_paragraph()
        p_ld.text = f"   {desc}"
        p_ld.font.size = Pt(10.5); p_ld.font.color.rgb = MUTED_TEXT

    # Right: Voice Synthesis & Elder UX
    add_card(s6, Inches(6.8), Inches(1.4), Inches(5.7), Inches(5.4))
    tb_v = s6.shapes.add_textbox(Inches(7.0), Inches(1.6), Inches(5.3), Inches(5.0))
    tf_v = tb_v.text_frame; tf_v.word_wrap = True
    p = tf_v.paragraphs[0]
    p.text = "🔊 Native Regional Speech Synthesis"
    p.font.size = Pt(17); p.font.bold = True; p.font.color.rgb = EMERALD

    audio_points = [
        ("Built for Illiterate & Senior Citizens", "Patients who cannot read prescription slips simply tap the 'Read Aloud' button to hear warnings in their own mother tongue."),
        ("Zero Network Audio Latency", "Uses browser and device-native Web Speech API with BCP 47 matching (te-IN, hi-IN, etc.) at a clear 0.88 cadence."),
        ("Plain-Language Explanations", "Alerts do not read raw medical jargon like 'Pharmacokinetic CYP2D6 inhibition'. They clearly speak: 'Danger: Warfarin and Aspirin should not be taken together because they cause severe bleeding.'"),
        ("Bilingual Doctor Card", "Generates a bilingual printable medication passport: headings in patient's language for family review, and clinical pharmacological notes in English for the attending doctor.")
    ]
    for atitle, adesc in audio_points:
        p_at = tf_v.add_paragraph()
        p_at.text = f"▶ {atitle}"
        p_at.font.size = Pt(13); p_at.font.bold = True; p_at.font.color.rgb = SLATE_DARK; p_at.space_before = Pt(10)
        p_ad = tf_v.add_paragraph()
        p_ad.text = adesc
        p_ad.font.size = Pt(11.5); p_ad.font.color.rgb = MUTED_TEXT; p_ad.space_before = Pt(2)

    set_notes(s6, "Speaker Notes: Demonstrate or describe language switching. In AushadhSeva, you switch from English to Telugu or Urdu in zero milliseconds because dictionaries are pre-compiled into the client. The 'Read Aloud' button speaks directly in Telugu or Hindi so elderly citizens who cannot read English medicine slips understand immediately.")

    # =========================================================================
    # SLIDE 7: ELDER ACCESSIBILITY & CITIZEN UX (WCAG AAA)
    # =========================================================================
    s7 = prs.slides.add_slide(blank_layout)
    add_header(s7, "Elder-Centric Accessibility & Citizen Experience (WCAG AAA)", slide_num=7)

    # 4 Feature Columns
    fw = Inches(2.7)
    fgap = Inches(0.3)
    ftop = Inches(1.4)
    fh = Inches(5.4)

    features = [
        ("Instant 'AA' Text Scaler", "Cycles Normal, Large (+15%), and Extra Large (+30%). Magnifies cards, inputs, and tables without horizontal page overflow or distortion.", MED_BLUE),
        ("Touch Targets (46-52px)", "Strict adherence to touch accessibility guidelines. Elderly users with shaky hands or arthritis can easily tap all action buttons.", EMERALD),
        ("OCR Strip Scanner", "Citizens or ward nurses can capture a photo of a blister strip or handwritten slip for automatic OCR character recognition.", AMBER),
        ("Doctor Health Passport", "One-click printable, bilingual summary card containing active medications, KDIGO eGFR stage, and emergency contact details.", DARK_NAVY)
    ]
    for i, (ftitle, fdesc, fcolor) in enumerate(features):
        pos_left = Inches(0.8) + i * (fw + fgap)
        add_card(s7, pos_left, ftop, fw, fh)
        tb_f = s7.shapes.add_textbox(pos_left + Inches(0.15), ftop + Inches(0.2), fw - Inches(0.3), fh - Inches(0.4))
        tf_f = tb_f.text_frame; tf_f.word_wrap = True
        
        p = tf_f.paragraphs[0]
        p.text = f"Feature {i+1}"
        p.font.size = Pt(11); p.font.bold = True; p.font.color.rgb = fcolor
        
        p_title = tf_f.add_paragraph()
        p_title.text = ftitle
        p_title.font.size = Pt(16); p_title.font.bold = True; p_title.font.color.rgb = SLATE_DARK; p_title.space_before = Pt(6)

        p_desc = tf_f.add_paragraph()
        p_desc.text = fdesc
        p_desc.font.size = Pt(12); p_desc.font.color.rgb = MUTED_TEXT; p_desc.space_before = Pt(10)

    set_notes(s7, "Speaker Notes: Focus on accessibility. Many healthcare apps fail because 70-year-olds cannot read 12px grey text. Our AA button scales the entire application by 15% and 30%. All touch targets are minimum 46 to 52 pixels tall, and the mobile bottom navigation bar puts essential actions within easy thumb reach.")

    # =========================================================================
    # SLIDE 8: PUBLIC HEALTH & PRADHAN MANTRI JAN AUSHADHI (PMBJP)
    # =========================================================================
    s8 = prs.slides.add_slide(blank_layout)
    add_header(s8, "Public Health Impact: Pradhan Mantri Jan Aushadhi (PMBJP) Savings", slide_num=8)

    # Left: Jan Aushadhi Savings
    add_card(s8, Inches(0.8), Inches(1.4), Inches(5.7), Inches(5.4))
    tb = s8.shapes.add_textbox(Inches(1.0), Inches(1.6), Inches(5.3), Inches(5.0))
    tf = tb.text_frame; tf.word_wrap = True
    
    p = tf.paragraphs[0]
    p.text = "Cutting Out-of-Pocket Medicine Costs by 70-85%"
    p.font.size = Pt(17); p.font.bold = True; p.font.color.rgb = EMERALD

    p_body = tf.add_paragraph()
    p_body.text = "In India, 60%+ of healthcare expenditure is out-of-pocket, largely driven by branded medicines. AushadhSeva automatically bridges commercial prescriptions to government Jan Aushadhi equivalents."
    p_body.font.size = Pt(12.5); p_body.font.color.rgb = MUTED_TEXT; p_body.space_before = Pt(8)

    p_ex = tf.add_paragraph()
    p_ex.text = "Real-World Jan Aushadhi Price Comparison:"
    p_ex.font.size = Pt(13); p_ex.font.bold = True; p_ex.font.color.rgb = SLATE_DARK; p_ex.space_before = Pt(12)

    pricing_data = [
        ("Metformin 500mg (10 tabs)", "Brand: ₹68", "PMBJP: ₹12", "82% Savings"),
        ("Atorvastatin 10mg (10 tabs)", "Brand: ₹110", "PMBJP: ₹18", "84% Savings"),
        ("Telmisartan 40mg (10 tabs)", "Brand: ₹95", "PMBJP: ₹14", "85% Savings"),
        ("Pantoprazole 40mg (10 tabs)", "Brand: ₹92", "PMBJP: ₹13", "86% Savings")
    ]
    for med, brand, pmbjp, sav in pricing_data:
        p_row = tf.add_paragraph()
        p_row.text = f"• {med}\n   {brand} ➔ {pmbjp} ({sav})"
        p_row.font.size = Pt(11.5); p_row.font.color.rgb = MED_BLUE; p_row.space_before = Pt(4)

    # Right: OpenFDA & RxNorm Live Medical APIs
    add_card(s8, Inches(6.8), Inches(1.4), Inches(5.7), Inches(5.4))
    tb_api = s8.shapes.add_textbox(Inches(7.0), Inches(1.6), Inches(5.3), Inches(5.0))
    tf_api = tb_api.text_frame; tf_api.word_wrap = True
    
    p = tf_api.paragraphs[0]
    p.text = "Global Interoperability: OpenFDA & NIH RxNorm"
    p.font.size = Pt(17); p.font.bold = True; p.font.color.rgb = MED_BLUE

    api_points = [
        ("Live OpenFDA Drug Safety Monograph", "Direct real-time query of clinical indications, boxed warnings, and adverse reactions from clinical registries."),
        ("NIH RxNorm Concept Identifiers (RxCUI)", "Assigns globally unique clinical drug codes to every recognized formulation for seamless hospital EHR exchange."),
        ("Jan Aushadhi Kendra Toll-Free Helpline", "Directly connects citizens to the government's national Jan Aushadhi locator helpline: 1800-180-8080."),
        ("Graceful Offline Fallback", "If internet connection is slow or drops in rural areas, the system continues running with zero disruption via its pre-compiled offline clinical tables.")
    ]
    for aptitle, apdesc in api_points:
        p_at = tf_api.add_paragraph()
        p_at.text = f"▶ {aptitle}"
        p_at.font.size = Pt(13); p_at.font.bold = True; p_at.font.color.rgb = SLATE_DARK; p_at.space_before = Pt(8)
        p_ad = tf_api.add_paragraph()
        p_ad.text = apdesc
        p_ad.font.size = Pt(11.5); p_ad.font.color.rgb = MUTED_TEXT; p_ad.space_before = Pt(2)

    set_notes(s8, "Speaker Notes: Point out the economic impact. Medication safety is not just clinical; it is financial. When AushadhSeva evaluates a drug like Telmisartan, it alerts the patient that buying the Jan Aushadhi generic equivalent cuts their medicine bill from 95 rupees to 14 rupees—an 85% saving. This brings true Swasth and Samavesh Bharat.")

    # =========================================================================
    # SLIDE 9: BENCHMARK RESULTS (Evaluation vs Baseline)
    # =========================================================================
    s9 = prs.slides.add_slide(blank_layout)
    add_header(s9, "Official Benchmark Performance: Doubling Diagnostic Effectiveness", slide_num=9)

    # 4 Highlight Metric Cards
    add_stat_box(s9, Inches(0.8), Inches(1.4), Inches(2.7), Inches(2.0), "0.8177", "Primary Score", "Baseline: 0.4071 (+100.8%)", val_color=EMERALD)
    add_stat_box(s9, Inches(3.8), Inches(1.4), Inches(2.7), Inches(2.0), "89.75%", "Serious-Issue Recall", "Baseline: 29.54% (+203.8%)", val_color=EMERALD)
    add_stat_box(s9, Inches(6.8), Inches(1.4), Inches(2.7), Inches(2.0), "75.56%", "Near-Miss Recall", "Baseline: 0.00% (New Capability)", val_color=MED_BLUE)
    add_stat_box(s9, Inches(9.8), Inches(1.4), Inches(2.7), Inches(2.0), "95.98%", "Alert Precision", "Only 4% false alarms", val_color=MED_BLUE)

    # Comparison Table
    add_card(s9, Inches(0.8), Inches(3.7), Inches(11.733), Inches(3.1))
    tb_tbl = s9.shapes.add_textbox(Inches(1.0), Inches(3.8), Inches(11.333), Inches(2.9))
    tf_tbl = tb_tbl.text_frame; tf_tbl.word_wrap = True

    p = tf_tbl.paragraphs[0]
    p.text = "Official H4 Benchmark Metrics vs Challenge Baseline (3,500 Test Cohorts)"
    p.font.size = Pt(15); p.font.bold = True; p.font.color.rgb = SLATE_DARK

    table_data = [
        ("Overall Primary Performance Score", "0.4071", "0.8177", "+100.8% Increase (Doubled diagnostic score)"),
        ("Serious-Issue Recall (Life-Threatening Conflicts)", "0.2954", "0.8975", "+203.8% Increase (Catches 9 of 10 lethal interactions)"),
        ("Near-Miss Recall (Silent Toxicities)", "0.0000", "0.7556", "Completely unhandled by baseline; now 75.6% resolved"),
        ("Alert Precision (False Alarm Suppression)", "0.9263", "0.9598", "96% of generated alerts are clinically actionable"),
        ("Graded Severity Recall (Correct Severity Tier)", "N/A", "0.9225", "Distinguishes fatal contraindications from mild cautions"),
        ("Brand & Generic Resolution Accuracy", "0.7787", "0.9648", "96.5% of noisy hospital transcription tokens mapped"),
        ("Misspelled Entry Resolution Accuracy", "0.0000", "0.8267", "Baseline failed completely on typos; AushadhSeva achieves 82.7%")
    ]
    for metric, base, ours, benefit in table_data:
        p_row = tf_tbl.add_paragraph()
        p_row.text = f"• {metric}: Baseline {base}  ➔  AushadhSeva {ours}  ({benefit})"
        p_row.font.size = Pt(11); p_row.font.color.rgb = SLATE_DARK; p_row.space_before = Pt(3)

    set_notes(s9, "Speaker Notes: These are the official numbers scored using the organisers' own evaluation script on 3,500 patient records. Point out the Serious-Issue Recall: from 29.5% to 89.8% — a 203% increase. And Near-Miss recall went from zero to 75.6%. Most importantly, Alert Precision is 96%, meaning doctors are not bombarded with useless spam.")

    # =========================================================================
    # SLIDE 10: PRODUCTION READINESS & TESTING
    # =========================================================================
    s10 = prs.slides.add_slide(blank_layout)
    add_header(s10, "Production Engineering: 46-Point Automated Functional Audit", slide_num=10)

    # Left: Test Audit Stats
    add_card(s10, Inches(0.8), Inches(1.4), Inches(4.5), Inches(5.4))
    tb_t = s10.shapes.add_textbox(Inches(1.0), Inches(1.6), Inches(4.1), Inches(5.0))
    tf_t = tb_t.text_frame; tf_t.word_wrap = True
    
    p = tf_t.paragraphs[0]
    p.text = "100% Passing Test Rate"
    p.font.size = Pt(18); p.font.bold = True; p.font.color.rgb = EMERALD

    p_sub = tf_t.add_paragraph()
    p_sub.text = "Every clinical algorithm, database model, API endpoint, and UI route is continuously validated by an automated audit suite."
    p_sub.font.size = Pt(12.5); p_sub.font.color.rgb = MUTED_TEXT; p_sub.space_before = Pt(6)

    test_modules = [
        ("Clinical Engine Unit Tests", "8 / 8 PASSED (100%)", EMERALD),
        ("Exhaustive System Audit", "46 / 46 PASSED (100%)", EMERALD),
        ("Multilingual Dictionaries", "8 Languages Validated", MED_BLUE),
        ("Production Bundle Size", "< 155 KB (Gzipped)", MED_BLUE),
        ("Dead / Unused Code", "0% (Completely Purged)", EMERALD)
    ]
    for mod, stat, col in test_modules:
        p_m = tf_t.add_paragraph()
        p_m.text = f"{mod}:"
        p_m.font.size = Pt(12.5); p_m.font.bold = True; p_m.font.color.rgb = SLATE_DARK; p_m.space_before = Pt(8)
        p_s = tf_t.add_paragraph()
        p_s.text = f"   ✔ {stat}"
        p_s.font.size = Pt(12); p_s.font.bold = True; p_s.font.color.rgb = col

    # Right: Verified Test Categories
    add_card(s10, Inches(5.6), Inches(1.4), Inches(6.9), Inches(5.4))
    tb_cats = s10.shapes.add_textbox(Inches(5.8), Inches(1.6), Inches(6.5), Inches(5.0))
    tf_cats = tb_cats.text_frame; tf_cats.word_wrap = True
    
    p = tf_cats.paragraphs[0]
    p.text = "Verified Endpoints & Modules"
    p.font.size = Pt(18); p.font.bold = True; p.font.color.rgb = SLATE_DARK

    test_cats = [
        ("Authentication & Sessions", "Tested demo logins (Eleanor, Robert, Grace), Admin portal, password hashing, and token invalidation."),
        ("Clinical Decision Engine", "Tested exact INN lookup, brand expansion, combination drugs, DDI rules, KDIGO renal staging, and unknown drug safety score suppression."),
        ("8 Multilingual Locales", "Verified all 364 translation keys in EN, TE, HI, TA, ML, KN, OR, UR with zero missing keys."),
        ("External Health APIs", "Live OpenFDA monographs, NIH RxNorm concept normalization, and PMBJP generic price differential calculation."),
        ("Formulary & Clinical Cohorts", "Verified 496 medicines catalog, patient eGFR filter, longitudinal prescription additions, and discontinuation flags."),
        ("Static SEO & Web Crawlers", "Verified robots.txt, sitemap.xml, vector og-image.svg, and responsive screen-fit across viewports.")
    ]
    for ctitle, cdesc in test_cats:
        p_ct = tf_cats.add_paragraph()
        p_ct.text = f"✔ {ctitle}"
        p_ct.font.size = Pt(12.5); p_ct.font.bold = True; p_ct.font.color.rgb = MED_BLUE; p_ct.space_before = Pt(6)
        p_cd = tf_cats.add_paragraph()
        p_cd.text = f"   {cdesc}"
        p_cd.font.size = Pt(11); p_cd.font.color.rgb = MUTED_TEXT; p_cd.space_before = Pt(1)

    set_notes(s10, "Speaker Notes: Explain code quality. This is not a prototype; it is production-grade software. We wrote a 46-point verification suite testing every user flow, login, clinical evaluation, and API response. All 46 pass with zero failures. The production bundle is under 155 kilobytes gzipped, loading in less than half a second on 3G mobile networks.")

    # =========================================================================
    # SLIDE 11: ETHICS, PRIVACY & CLINICAL GOVERNANCE
    # =========================================================================
    s11 = prs.slides.add_slide(blank_layout)
    add_header(s11, "Clinical Ethics, Data Privacy & Governance Guardrails", slide_num=11)

    # 3 Wide Cards
    add_card(s11, Inches(0.8), Inches(1.4), Inches(11.733), Inches(1.6))
    tb1 = s11.shapes.add_textbox(Inches(1.0), Inches(1.5), Inches(11.3), Inches(1.4))
    tf1 = tb1.text_frame; tf1.word_wrap = True
    tf1.paragraphs[0].text = "1. Human-in-the-Loop Clinical Autonomy (Decision Support, Not Replacement)"
    tf1.paragraphs[0].font.size = Pt(15); tf1.paragraphs[0].font.bold = True; tf1.paragraphs[0].font.color.rgb = MED_BLUE
    p = tf1.add_paragraph()
    p.text = "AushadhSeva never automatically discontinues, alters, or prescribes any drug. It strictly acts as a decision support aid for qualified clinicians. Every alert provides explicit pharmacological rationale, evidence citations, and confidence limitations for clinician review."
    p.font.size = Pt(12); p.font.color.rgb = MUTED_TEXT; p.space_before = Pt(4)

    add_card(s11, Inches(0.8), Inches(3.2), Inches(11.733), Inches(1.6))
    tb2 = s11.shapes.add_textbox(Inches(1.0), Inches(3.3), Inches(11.3), Inches(1.4))
    tf2 = tb2.text_frame; tf2.word_wrap = True
    tf2.paragraphs[0].text = "2. 100% Offline-First Execution & DPDP Act 2023 Compliance"
    tf2.paragraphs[0].font.size = Pt(15); tf2.paragraphs[0].font.bold = True; tf2.paragraphs[0].font.color.rgb = EMERALD
    p = tf2.add_paragraph()
    p.text = "Zero Protected Health Information (PHI) is transmitted to third-party commercial LLMs or external cloud endpoints during clinical decision-making. All pairwise checks run locally inside the hospital network or mobile device, ensuring full compliance with India's Digital Personal Data Protection (DPDP) Act."
    p.font.size = Pt(12); p.font.color.rgb = MUTED_TEXT; p.space_before = Pt(4)

    add_card(s11, Inches(0.8), Inches(5.0), Inches(11.733), Inches(1.6))
    tb3 = s11.shapes.add_textbox(Inches(1.0), Inches(5.1), Inches(11.3), Inches(1.4))
    tf3 = tb3.text_frame; tf3.word_wrap = True
    tf3.paragraphs[0].text = "3. Auditability, Explainability & Forensic Logging"
    tf3.paragraphs[0].font.size = Pt(15); tf3.paragraphs[0].font.bold = True; tf3.paragraphs[0].font.color.rgb = SLATE_DARK
    p = tf3.add_paragraph()
    p.text = "Unlike 'black-box' deep learning models that produce unpredictable text, every decision in AushadhSeva is mapped directly to a transparent rule ID and verified clinical reference dataset. Clinicians can immediately review the exact mechanism, clinical notes, and KDIGO thresholds."
    p.font.size = Pt(12); p.font.color.rgb = MUTED_TEXT; p.space_before = Pt(4)

    set_notes(s11, "Speaker Notes: Address the AI ethics question that juries always ask: 'What about liability and hallucinations?'. Explain that AushadhSeva is an explainable decision support tool. We never alter prescriptions automatically. And because it runs completely offline without sending patient names to OpenAI or Google Cloud, patient privacy under India's DPDP Act is 100% protected.")

    # =========================================================================
    # SLIDE 12: PUBLIC HEALTH ROADMAP & FUTURE SCOPE
    # =========================================================================
    s12 = prs.slides.add_slide(blank_layout)
    add_header(s12, "Future Scope: National Public Health Integration Roadmap", slide_num=12)

    # 3 Roadmap Columns
    rw = Inches(3.7)
    rgap = Inches(0.3)
    rtop = Inches(1.4)
    rh = Inches(5.4)

    # Phase 1
    add_card(s12, Inches(0.8), rtop, rw, rh)
    tb = s12.shapes.add_textbox(Inches(1.0), rtop + Inches(0.2), rw - Inches(0.4), rh - Inches(0.4))
    tf = tb.text_frame; tf.word_wrap = True
    tf.paragraphs[0].text = "Phase 1: Present (SFIC 2026)"
    tf.paragraphs[0].font.size = Pt(15); tf.paragraphs[0].font.bold = True; tf.paragraphs[0].font.color.rgb = EMERALD
    
    p1_items = [
        ("Production Web & Mobile App", "Responsive, elder-accessible PWA deployed on hospital desktop and smartphones."),
        ("H4 Deterministic Pipeline", "Doubled baseline score with 90% serious recall on 3,500 test patients."),
        ("8 Indian Languages + TTS", "Native vernacular voice readout and text directionality."),
        ("Jan Aushadhi PMBJP Cost Calculator", "Empowers citizens with 80%+ savings alternatives.")
    ]
    for ititle, idesc in p1_items:
        p_t = tf.add_paragraph()
        p_t.text = f"✔ {ititle}"
        p_t.font.size = Pt(12); p_t.font.bold = True; p_t.font.color.rgb = SLATE_DARK; p_t.space_before = Pt(8)
        p_d = tf.add_paragraph()
        p_d.text = idesc
        p_d.font.size = Pt(10.5); p_d.font.color.rgb = MUTED_TEXT

    # Phase 2
    add_card(s12, Inches(0.8) + rw + rgap, rtop, rw, rh)
    tb = s12.shapes.add_textbox(Inches(1.0) + rw + rgap, rtop + Inches(0.2), rw - Inches(0.4), rh - Inches(0.4))
    tf = tb.text_frame; tf.word_wrap = True
    tf.paragraphs[0].text = "Phase 2: Next 6 Months"
    tf.paragraphs[0].font.size = Pt(15); tf.paragraphs[0].font.bold = True; tf.paragraphs[0].font.color.rgb = MED_BLUE
    
    p2_items = [
        ("Ayushman Bharat (ABHA / ABDM)", "Link citizen prescriptions directly with their 14-digit ABHA Health ID for seamless longitudinal records."),
        ("FHIR Interoperability Standard", "Export clinical reconciliation records in HL7 FHIR format for seamless hospital integration."),
        ("Offline Android Tablet App for ASHA", "Lightweight offline APK for community health workers conducting village polypharmacy audits."),
        ("Jan Aushadhi Kendra Integration", "Barcode scanner linking directly to Kendra point-of-sale inventory.")
    ]
    for ititle, idesc in p2_items:
        p_t = tf.add_paragraph()
        p_t.text = f"▶ {ititle}"
        p_t.font.size = Pt(12); p_t.font.bold = True; p_t.font.color.rgb = SLATE_DARK; p_t.space_before = Pt(8)
        p_d = tf.add_paragraph()
        p_d.text = idesc
        p_d.font.size = Pt(10.5); p_d.font.color.rgb = MUTED_TEXT

    # Phase 3
    add_card(s12, Inches(0.8) + (rw + rgap) * 2, rtop, rw, rh)
    tb = s12.shapes.add_textbox(Inches(1.0) + (rw + rgap) * 2, rtop + Inches(0.2), rw - Inches(0.4), rh - Inches(0.4))
    tf = tb.text_frame; tf.word_wrap = True
    tf.paragraphs[0].text = "Phase 3: National Scale"
    tf.paragraphs[0].font.size = Pt(15); tf.paragraphs[0].font.bold = True; tf.paragraphs[0].font.color.rgb = DARK_NAVY
    
    p3_items = [
        ("e-Sanjeevani Teleconsultation Bridge", "Integrate into the national teleconsultation network to assist doctors during remote calls."),
        ("National Pharmacovigilance (PvPI)", "Automated adverse drug reaction (ADR) detection and reporting pipeline to IPC/PvPI."),
        ("State District Hospital EMR Rollout", "Native plugin for NIC's e-Hospital hospital management system across 700+ district hospitals.")
    ]
    for ititle, idesc in p3_items:
        p_t = tf.add_paragraph()
        p_t.text = f"★ {ititle}"
        p_t.font.size = Pt(12); p_t.font.bold = True; p_t.font.color.rgb = SLATE_DARK; p_t.space_before = Pt(10)
        p_d = tf.add_paragraph()
        p_d.text = idesc
        p_d.font.size = Pt(10.5); p_d.font.color.rgb = MUTED_TEXT

    set_notes(s12, "Speaker Notes: Outline the future roadmap clearly. Phase 1 is done and live today. In Phase 2, we integrate with Ayushman Bharat ABHA IDs and deploy an offline tablet app for ASHA workers. In Phase 3, we connect to e-Sanjeevani and NIC's e-Hospital system, scaling to district hospitals nationwide.")

    # =========================================================================
    # SLIDE 13: CONCLUSION & WHY TEAM ARROW SHOULD WIN
    # =========================================================================
    s13 = prs.slides.add_slide(blank_layout)
    set_slide_background(s13, DARK_NAVY)

    # Top Badge
    b13 = s13.shapes.add_shape(MSO_SHAPE.ROUNDED_RECTANGLE, Inches(0.8), Inches(1.0), Inches(3.2), Inches(0.45))
    b13.fill.solid(); b13.fill.fore_color.rgb = RGBColor(3, 105, 161)
    b13.line.color.rgb = SKY_BLUE
    p_b13 = b13.text_frame.paragraphs[0]
    p_b13.text = "THE WINNING VALUE PROPOSITION"
    p_b13.font.name = 'Arial'; p_b13.font.size = Pt(11); p_b13.font.bold = True; p_b13.font.color.rgb = WHITE
    p_b13.alignment = PP_ALIGN.CENTER

    # Title
    tb_c = s13.shapes.add_textbox(Inches(0.8), Inches(1.6), Inches(11.733), Inches(1.4))
    tf_c = tb_c.text_frame
    p_ct = tf_c.paragraphs[0]
    p_ct.text = "Why AushadhSeva Delivers on the Mission"
    p_ct.font.name = 'Arial'; p_ct.font.size = Pt(36); p_ct.font.bold = True; p_ct.font.color.rgb = WHITE

    p_csub = tf_c.add_paragraph()
    p_csub.text = "The only solution combining proven clinical rigor with deep pan-Indian vernacular inclusion."
    p_csub.font.name = 'Arial'; p_csub.font.size = Pt(18); p_csub.font.color.rgb = SKY_BLUE; p_csub.space_before = Pt(4)

    # 4 Highlights Grid
    cw13 = Inches(2.7)
    cgap13 = Inches(0.3)
    ctop13 = Inches(3.2)
    ch13 = Inches(2.6)

    pillars = [
        ("Clinical Rigor", "0.8177 primary score (doubled baseline), 90% serious recall, and 75.6% near-miss recall with zero alert fatigue.", EMERALD),
        ("True Inclusion", "8 Indian languages with native regional voice readout, RTL Urdu support, and WCAG AAA elder-accessibility.", SKY_BLUE),
        ("Economic Impact", "Integrated Jan Aushadhi (PMBJP) savings cutting out-of-pocket costs by up to 85% for vulnerable citizens.", AMBER),
        ("Production Ready", "100% clean code, 46/46 passing tests, responsive mobile/desktop UI, and fully documented repository.", WHITE)
    ]
    for i, (ptitle, pdesc, pcol) in enumerate(pillars):
        pleft = Inches(0.8) + i * (cw13 + cgap13)
        c = s13.shapes.add_shape(MSO_SHAPE.ROUNDED_RECTANGLE, pleft, ctop13, cw13, ch13)
        c.fill.solid(); c.fill.fore_color.rgb = RGBColor(15, 23, 42)
        c.line.color.rgb = RGBColor(51, 65, 85); c.line.width = Pt(1.5)
        
        tb_card = s13.shapes.add_textbox(pleft + Inches(0.15), ctop13 + Inches(0.15), cw13 - Inches(0.3), ch13 - Inches(0.3))
        tf_card = tb_card.text_frame; tf_card.word_wrap = True
        
        p = tf_card.paragraphs[0]
        p.text = ptitle
        p.font.size = Pt(16); p.font.bold = True; p.font.color.rgb = pcol
        
        p_d = tf_card.add_paragraph()
        p_d.text = pdesc
        p_d.font.size = Pt(11.5); p_d.font.color.rgb = RGBColor(203, 213, 225); p_d.space_before = Pt(8)

    # Final Quote
    tb_q = s13.shapes.add_textbox(Inches(0.8), Inches(6.1), Inches(11.733), Inches(0.8))
    p_q = tb_q.text_frame.paragraphs[0]
    p_q.text = "“Swasth & Samavesh Bharat starts with safe medication for every citizen.”"
    p_q.font.name = 'Arial'; p_q.font.size = Pt(18); p_q.font.italic = True; p_q.font.bold = True; p_q.font.color.rgb = SKY_BLUE
    p_q.alignment = PP_ALIGN.CENTER

    p_end = tb_q.text_frame.add_paragraph()
    p_end.text = "Thank you! We welcome questions from the distinguished jury.  •  Team Arrow (Dev02005)"
    p_end.font.name = 'Arial'; p_end.font.size = Pt(13); p_end.font.color.rgb = WHITE
    p_end.alignment = PP_ALIGN.CENTER
    p_end.space_before = Pt(4)

    set_notes(s13, "Speaker Notes: Closing remarks. Thank the jury. Reiterate our key achievements: doubled the baseline score, caught 90% of serious issues, eliminated alert fatigue, and built the only platform in India that talks to grandmothers in Telugu, Hindi, or Tamil with native voice synthesis. We are ready for your questions.")

    prs.save(output_path)
    print(f"[SUCCESS] Presentation generated successfully: {output_path}")

if __name__ == '__main__':
    out = os.path.abspath(sys.argv[1] if len(sys.argv) > 1 else 'AushadhSeva_Jury_Presentation.pptx')
    build_presentation(out)
