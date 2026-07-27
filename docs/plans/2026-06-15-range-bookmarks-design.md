# Design Document: Range-Based Bookmark System for Surgical Updates

This document outlines the design and implementation of range-based bookmarks for the SecureCAT-v2 Capstone manuscript update pipeline.

## 1. Problem Statement

The legacy surgical update mechanism in `md_to_docx.py` treats a bookmarked section as spanning from the `w:bookmarkStart` of a section heading to the `w:bookmarkStart` of the next section heading. 

This causes two major issues:
1. **Destructive Updates:** Any manually inserted content in Word (such as custom tables, shapes, and the Input-Process-Output (IPO) diagram/caption) that sits between subheadings is permanently deleted during updates because the parser wipes out everything between the two headings.
2. **Lack of Boundary Isolation:** The document has no notion of "free space" or unallocated sections where co-authors can safely append comments, diagrams, or annotations in Word without risking their deletion on the next sync.

---

## 2. Proposed Solution (Approach 1: Range-Based Bookmarks)

We will transition the bookmark structure from point-bookmarks (both start and end tags inside the heading paragraph) to range-bookmarks (where the end tag is placed at the end of the last paragraph of the section content).

### 2.1 Range Definition
A section `tag_name` is bounded by:
* **Start:** `<w:bookmarkStart w:name="tag_tag_name" w:id="ID"/>` inside the heading paragraph.
* **End:** `<w:bookmarkEnd w:id="ID"/>` inside the *last paragraph* of the section's body content.

### 2.2 Boundary Detection Logic
When looking up a section:
1. Locate the heading paragraph containing `w:bookmarkStart` with `w:name="tag_tag_name"`.
2. Locate the matching `w:bookmarkEnd` by matching the `w:id` attribute.
3. If the matching `w:bookmarkEnd` is found and is in a paragraph *after* the start paragraph:
   * **Range Bookmark:** The section content covers paragraphs `[start_idx + 1, end_idx]`.
4. Otherwise (if no end tag is found or it's inside the same heading paragraph):
   * **Point Bookmark (Fallback):** The section content falls back to the old behavior, extending up to the start of the next section's bookmark. This enables self-healing migration.

---

## 3. Detailed Component Patches

### 3.1 Parser & Core Converter (`capstone/manuscript/md_to_docx.py`)

* **`find_section_paragraphs`**: Rewrite to return `(start_idx, end_idx, is_point_bookmark)`. Lookup matching `w:bookmarkEnd` by ID first, and fallback to next `bookmarkStart` if it's a point bookmark.
* **`delete_paragraphs` / `extract_existing_text`**: Call them with adjusted `end_idx` limits. For range bookmarks, target `end_idx + 1` to include the final paragraph of the section in the operation.
* **`insert_section_content`**: Recreate the `w:bookmarkEnd` on the last paragraph of the newly inserted content to ensure the bookmark range stays synchronized with the updated content.

### 3.2 Migration & Rebuild Scripts

* **`capstone/manuscript/add_bookmarks.py`**: Modify to find the last paragraph of each section (the paragraph right before the next heading) and place the `w:bookmarkEnd` there.
* **`capstone/manuscript/rebuild_docx_from_md.py`**: Update step 6 to generate range bookmarks instead of point bookmarks.
* **`capstone/manuscript/fix_and_verify_pipeline.py`**: Update `insert_clean_bookmarks` to write proper range bookmarks.

---

## 4. Verification & Regression Plan

1. **Unit Testing:** Update `capstone/manuscript/test_md_to_docx.py` to add range bookmark cases and verify boundary isolation.
2. **Surgical Verification:** Perform a test update on a copy of the manuscript and verify that the IPO section at paragraph index 31-33 is completely untouched.
3. **Regression Audit:** Ensure the `regression_audit.py` (if applicable) or the check pipeline passes.
