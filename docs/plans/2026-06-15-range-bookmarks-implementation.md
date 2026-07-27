# Range-Based Bookmark System Implementation Plan

> **For Antigravity:** REQUIRED WORKFLOW: Use `.agent/workflows/execute-plan.md` to execute this plan in single-flow mode.

**Goal:** Implement range-based bookmarks inside the manuscript MD-to-DOCX conversion pipeline to ensure that manually inserted Word content (like diagrams or tables) placed between section boundaries is never overwritten or deleted.

**Architecture:** We will lookup and respect the `w:bookmarkEnd` elements by matching ID in `md_to_docx.py`, falling back to point-bookmark behavior (stopping at the next heading bookmark start) for self-healing backward compatibility. Newly updated sections will write their `w:bookmarkEnd` on the last newly inserted paragraph. The re-bookmarking scripts will be updated to write range bookmarks.

**Tech Stack:** Python 3, `python-docx` (OOXML manipulation), python standard library.

---

### Task 1: Add Unit Tests for Range Bookmarks

**Files:**
- Modify: `capstone/manuscript/test_md_to_docx.py:579-603` (and append test case)

**Step 1: Write the failing test**
We will add `test_range_bookmarks()` to `test_md_to_docx.py` and register it in `run_all_tests()`. The test creates a document, places range and point bookmarks, performs a surgical update, and asserts that content outside the range bookmarks is untouched.

```python
def test_range_bookmarks():
    """Test range-based bookmarks behavior and fallback point-bookmark behavior."""
    from docx import Document
    from docx.oxml import OxmlElement
    from docx.oxml.ns import qn
    from md_to_docx import find_section_paragraphs, insert_section_content, apply_updates

    # Create dummy doc
    doc = Document()
    
    # 1. Add point bookmark section
    p1 = doc.add_paragraph("Chapter 1", style="Heading 1")
    # bookmarkStart
    s1 = OxmlElement('w:bookmarkStart')
    s1.set(qn('w:id'), '1001')
    s1.set(qn('w:name'), 'tag_ch1_introduction')
    p1._p.insert(0, s1)
    # bookmarkEnd in same para (point bookmark)
    e1 = OxmlElement('w:bookmarkEnd')
    e1.set(qn('w:id'), '1001')
    p1._p.append(e1)
    
    doc.add_paragraph("Intro body 1", style="Normal")
    doc.add_paragraph("Intro body 2", style="Normal")

    # 2. Add range bookmark section
    p2 = doc.add_paragraph("Conceptual Framework of the Study", style="Heading 2")
    # bookmarkStart
    s2 = OxmlElement('w:bookmarkStart')
    s2.set(qn('w:id'), '1002')
    s2.set(qn('w:name'), 'tag_ch1_conceptual_framework')
    p2._p.insert(0, s2)
    
    # Body paragraph of range section
    p2_body = doc.add_paragraph("Framework body text", style="Normal")
    
    # bookmarkEnd placed in the last body paragraph of the section
    e2 = OxmlElement('w:bookmarkEnd')
    e2.set(qn('w:id'), '1002')
    p2_body._p.append(e2)

    # 3. Add manual unallocated content (should NOT be inside any range bookmark!)
    doc.add_paragraph("[IPO Diagram Image]", style="Normal")
    doc.add_paragraph("Figure 1. Diagram Caption", style="Normal")

    # 4. Add next heading to define end of the document sections
    p3 = doc.add_paragraph("Objectives of the Study", style="Heading 2")
    s3 = OxmlElement('w:bookmarkStart')
    s3.set(qn('w:id'), '1003')
    s3.set(qn('w:name'), 'tag_ch1_objectives')
    p3._p.insert(0, s3)
    e3 = OxmlElement('w:bookmarkEnd')
    e3.set(qn('w:id'), '1003')
    p3._p.append(e3)

    # Verify boundary detection
    # tag_ch1_introduction is a point bookmark (fallback to next bookmark start tag_ch1_conceptual_framework)
    start_idx_1, end_idx_1, is_point_1 = find_section_paragraphs(doc, 'tag_ch1_introduction', {'heading': 'Chapter 1'})
    assert is_point_1 is True
    # Should stop at next heading paragraph (Conceptual Framework = index 3)
    # Paragraphs: 0=Chapter 1, 1=Intro body 1, 2=Intro body 2
    assert start_idx_1 == 0
    assert end_idx_1 == 3 

    # tag_ch1_conceptual_framework is a range bookmark (ends at Framework body text = index 4)
    start_idx_2, end_idx_2, is_point_2 = find_section_paragraphs(doc, 'tag_ch1_conceptual_framework', {'heading': 'Conceptual Framework of the Study'})
    assert is_point_2 is False
    assert start_idx_2 == 3
    assert end_idx_2 == 4

    # Perform update on the range section tag_ch1_conceptual_framework
    tags = {
        'ch1_conceptual_framework': {
            'heading': 'Conceptual Framework of the Study',
            'level': 2,
            'update': 'New Framework Text 1\n\nNew Framework Text 2',
            'remove': None,
            'raw_content': ''
        }
    }
    
    apply_updates(doc, tags, ['ch1_conceptual_framework'])
    
    # Assertions on updated document:
    # 1. New content paragraphs inserted
    # 2. [IPO Diagram Image] at index 7/8 is NOT deleted because it was outside the range bookmark boundary!
    all_texts = [p.text for p in doc.paragraphs]
    assert "New Framework Text 1" in all_texts
    assert "New Framework Text 2" in all_texts
    assert "[IPO Diagram Image]" in all_texts
    assert "Figure 1. Diagram Caption" in all_texts
    
    # Assert bookmarkEnd was correctly recreated at the last paragraph of new content
    new_end_idx = -1
    for i, p in enumerate(doc.paragraphs):
        if p._p.xpath('.//w:bookmarkEnd[@w:id="1002"]'):
            new_end_idx = i
            break
    
    assert new_end_idx != -1
    assert doc.paragraphs[new_end_idx].text == "New Framework Text 2"
    
    print("✅ test_range_bookmarks passed")
```

**Step 2: Run test to verify it fails**

Run: `python3 capstone/manuscript/test_md_to_docx.py`
Expected: FAIL due to import/compilation error (e.g. `find_section_paragraphs` not returning 3 elements, or `apply_updates` failure).

**Step 3: Write minimal implementation**
*(Will be done in Task 2 below)*

---

### Task 2: Implement Range-Based Bookmarks in `md_to_docx.py`

**Files:**
- Modify: `capstone/manuscript/md_to_docx.py:267-308` (find_section_paragraphs)
- Modify: `capstone/manuscript/md_to_docx.py:416-460` (insert_section_content)
- Modify: `capstone/manuscript/md_to_docx.py:487-550` (apply_updates)

**Step 1: Write minimal implementation in `md_to_docx.py`**
1. Modify `find_section_paragraphs` to:
   * Look up matching `w:bookmarkEnd` by ID.
   * If `end_idx > start_idx`, return `(start_idx, end_idx, False)`.
   * Otherwise, fallback to the next bookmark start and return `(start_idx, next_bm_idx, True)`.
2. Modify `insert_section_content` to:
   * Accept `bookmark_name` and `is_point` parameter.
   * Delete paragraphs up to `end_idx` (if `is_point` is True) or `end_idx + 1` (if `is_point` is False).
   * Place `w:bookmarkEnd` with matching ID on `last_inserted_p` (if any paragraphs inserted) or `heading_p` (if empty).
3. Modify `apply_updates` to unpack the 3-tuple return from `find_section_paragraphs` and pass boundaries accordingly.

**Step 2: Run tests to verify they pass**

Run: `python3 capstone/manuscript/test_md_to_docx.py`
Expected: PASS

**Step 3: Commit**

```bash
git add capstone/manuscript/md_to_docx.py capstone/manuscript/test_md_to_docx.py
git commit -m "feat: implement range-based bookmarks in md_to_docx core"
```

---

### Task 3: Update Bookmark Management Scripts

**Files:**
- Modify: `capstone/manuscript/add_bookmarks.py:53-92`
- Modify: `capstone/manuscript/rebuild_docx_from_md.py:380-410`
- Modify: `capstone/manuscript/fix_and_verify_pipeline.py:136-180`

**Step 1: Write range bookmark logic in migration/rebuild scripts**
In all three scripts, update the bookmark insertion logic so that instead of adding both `bookmarkStart` and `bookmarkEnd` to the heading paragraph, it finds the last paragraph before the next section's heading and places the `bookmarkEnd` there:

```python
def add_range_bookmark(start_para, end_para, tag_name):
    bm_name = f'tag_{sanitize(tag_name)}'
    bm_id = str(abs(hash(bm_name)) % 1000000)
    
    start = OxmlElement('w:bookmarkStart')
    start.set(qn('w:id'), bm_id)
    start.set(qn('w:name'), bm_name)
    start_para._p.insert(0, start)
    
    end = OxmlElement('w:bookmarkEnd')
    end.set(qn('w:id'), bm_id)
    end.set(qn('w:name'), bm_name)
    
    if start_para is end_para:
        start_para._p.append(end)
    else:
        end_para._p.append(end)
```

**Step 2: Run tests**
Verify tests still pass.
Expected: PASS

**Step 3: Commit**

```bash
git add capstone/manuscript/add_bookmarks.py capstone/manuscript/rebuild_docx_from_md.py capstone/manuscript/fix_and_verify_pipeline.py
git commit -m "feat: update add_bookmarks and rebuild scripts to write range bookmarks"
```

---

### Task 4: Run Verification and Audit Pipeline

**Step 1: run immediate migration script**
Migrate the master document's bookmarks to proper range bookmarks:
```bash
python3 capstone/manuscript/add_bookmarks.py
```

**Step 2: Run verification pipeline**
```bash
python3 capstone/manuscript/skills/capstone-manuscript-pipeline/scripts/pipeline.py --action check
```
Expected: PASS/DRIFT (if sync matches).

**Step 3: Verify Conceptual Framework & IPO preservation**
Run a test update using the pipeline:
```bash
python3 capstone/manuscript/skills/capstone-manuscript-pipeline/scripts/pipeline.py --action update
```
Verify via git diff or inspecting the generated DOCX that the IPO diagram and caption at paragraphs 31-33 are intact.
