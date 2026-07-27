#!/usr/bin/env python3
"""Compile all capstone markdown files into a single capstone.md document."""

from pathlib import Path
import subprocess

CAPSTONE_DIR = Path("/home/user/Projects/SecureCAT-v2/capstone")
OUTPUT_PATH = Path("/home/user/Projects/SecureCAT-v2/capstone.md")

# Define file structure
FILES = [
    ("PROJECT OVERVIEW", [
        "README.md",
        "ROADMAP.md",
        "SYSTEM_FEATURES.md",
    ]),
    ("GUIDES", [
        "guides/GUIDE-1-FORMATTING.md",
        "guides/GUIDE-2-CHAPTER1-CONTENT.md",
        "guides/GUIDE-3-CHAPTER2-CONTENT.md",
        "guides/GUIDE-4-AIDLC-DEFENSE.md",
        "guides/GUIDE-5-CHECKLIST.md",
        "guides/GUIDE-6-CHAPTER3-ADVANCE.md",
    ]),
    ("RESEARCH", [
        "research/Chapter_1_2_Drafting_Plan.md",
        "research/Existing_and_Planned_Features.md",
        "research/RESEARCH_ARGUMENT_BANK.md",
        "research/RESEARCH_DIRECTION_ANALYSIS.md",
        "research/SEARCH_TERM_CHEAT_SHEET.md",
    ]),
    ("STRATEGY", [
        "strategy/pre_proposal_defense.md",
    ]),
    ("TEAM COORDINATION", [
        "team_meta/CAPTAINING_OVERVIEW.md",
        "team_meta/COMPREHENSIVE_TASK_REPORT.md",
        "team_meta/TEAM_META_GUIDE_Ch1_Ch2.md",
        "team_meta/TASK_DISTRIBUTION_PLAN.md",
        "team_meta/TEAM_TASK_CHECKLIST.md",
    ]),
    ("MEMBER DIRECTIONS", [
        "team_meta/members/david/DIRECTION.md",
        "team_meta/members/christine/DIRECTION.md",
        "team_meta/members/jaypee/DIRECTION.md",
    ]),
    ("SELF-ASSESSMENTS", [
        "team_meta/Session 1 - Meta Guide Responses/david_self_assessment.md",
        "team_meta/Session 1 - Meta Guide Responses/christine_self_assessment.md",
    ]),
]

HEADER = """# SecureCAT-v2 Capstone Project - Complete Documentation

> **Generated:** June 2, 2026  |  **Project:** BSIT Capstone - SecureCAT-v2  |  **Team:** David (Team Lead), Christine, Jaypee

---

## 📋 Table of Contents

1. [Project Overview & Roadmap](#project-overview)
2. [System Features](#system-features)
3. [Writing Guides (GUIDE-1 through GUIDE-6)](#writing-guides)
4. [Research Materials](#research-materials)
5. [Strategy & Defense Planning](#strategy)
6. [Team Coordination & Task Management](#team-coordination)
7. [Member Direction Files](#member-directions)
8. [Self-Assessments](#self-assessments)
9. [Supporting Assets](#supporting-assets)

---

"""

FOOTER = """

---

## 📁 Supporting Assets (Referenced)

### 📊 index.html - Interactive Dashboard & Gantt Chart

**Location:** `capstone/index.html`

A single-page HTML application providing:
- **Task Timeline & Gantt Chart**: Visualizes task distribution across the writing sprint (June 1–10, 2026)
- **Team Task Cards**: Color-coded by assignee:
  - David: Blue (#3b82f6)
  - Christine: Green (#10b981)
  - Jaypee: Orange (#f59e0b)
- **Chapter Organization**: Tasks grouped by Chapter 1, Chapter 2, and Cross-Chapter work
- **Interactive Elements**: Clickable task bars, status filtering, responsive desktop/mobile views

The Gantt chart displays project timeline with daily granularity, showing task bars, deadlines, and progress tracking.

### 📄 Template Documents

- **BSIT Capstone Template.docx**: Official BSIT capstone manuscript template
- **SecureCAT Letter.docx**: Project documentation/letter

*Note: Binary files (.docx) and the interactive HTML dashboard are referenced for context but not included in this markdown compilation.*

---

**End of SecureCAT-v2 Capstone Documentation Compilation**
"""


def read_file_content(file_path: Path) -> str:
    """Read file content using cat command."""
    try:
        result = subprocess.run(
            ['cat', str(file_path)],
            capture_output=True,
            text=True,
            timeout=5
        )
        return result.stdout
    except Exception as e:
        return f"\n⚠️ *Error reading {file_path.name}: {e}*\n"


def main():
    print("Compiling capstone documentation...")
    
    content_parts = [HEADER]
    
    for section_name, files in FILES:
        content_parts.append(f"\n## {section_name}\n")
        
        for file_path in files:
            full_path = CAPSTONE_DIR / file_path
            
            if not full_path.exists():
                print(f"⚠️ File not found: {file_path}")
                continue
            
            filename = Path(file_path).name
            file_content = read_file_content(full_path)
            
            content_parts.append(f"\n### 📄 {filename}\n")
            content_parts.append(file_content)
            content_parts.append("\n")
            
            print(f"✓ Processed {file_path}")
    
    content_parts.append(FOOTER)
    
    final_content = "\n".join(content_parts)
    
    # Write output
    OUTPUT_PATH.write_text(final_content)
    
    print(f"\n✓ Created capstone.md")
    print(f"✓ Size: {len(final_content):,} characters")
    print(f"✓ Location: {OUTPUT_PATH}")


if __name__ == "__main__":
    main()
