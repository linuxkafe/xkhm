---
ticket: T010
phase: learn
status: done
created: 2026-10-20
requires:
  - aes/tickets/T010.md
produces:
  - aes/tickets/T010-learn.md
  - aes/shadow/SD-DOMAIN-008.md
side_effects:
  - updates aes/kanban.md
blocked_by: ''
---

# T010 — Learn: Announcements & Knowledgebase

## What Was Done

Implemented announcements and knowledgebase modules for xkhm — public-facing content management.

### Implementation Details

1. **Knowledgebase** (`src/Service/KnowledgebaseService.php`, `src/Http/Controller/KnowledgebaseController.php`)
   - `getCategories()`: List all knowledgebase categories
   - `getArticlesByCategory()`: Get articles for a category
   - `getArticle()`: Fetch single article
   - `searchArticles()`: Search articles by keyword
   - `getRecentArticles()`: Latest published articles
   - Portuguese UI: "Central de Ajuda", "Buscar artigos", "Categoria"

2. **Announcements** (`src/Service/AnnouncementService.php`, `src/Http/Controller/AnnouncementController.php`)
   - `getRecentAnnouncements()`: List published announcements
   - `getAnnouncement()`: Fetch single announcement
   - Portuguese UI: "Notícias", "Data", "Voltar"

### Quality Gates

- **Tests**: 21 total, 46 assertions — **all pass**
- **Lint**: PHPStan Level 5 — **0 errors**
- **Format**: PHP-CS-Fixer — **clean**
- **All gates**: `make check` passes

## Key Findings

### Knowledgebase Structure

WHMCS knowledgebase uses:
- `tblknowledgebasecats`: Categories with sort order
- `tblknowledgebase`: Articles with published/unpublished status
- Articles linked to categories via `categoryid`
- Search via LIKE queries on title and description

### Announcements Structure

WHMCS announcements:
- `tblannouncements`: All announcements
- `published` field: 1 = live, 0 = draft
- Simple list view and individual article view
- No categories or tags

### Public vs Authenticated

These modules are **public-facing** — no authentication required:
- Anyone can read announcements
- Anyone can browse knowledgebase
- Only tickets require login

## Feynman Method

### For a Child

The knowledgebase is like a library of answers. You can browse by topic (categories) or search for what you need. Announcements are like notice board posts — important news for everyone to see.

### For an Expert

The KnowledgebaseService follows a simple repository pattern with category-based article retrieval and full-text search. The AnnouncementService is a thin wrapper around `tblannouncements` with published status filtering.

## Chain of Whys

- **Why no admin CRUD?** → Admin panel deferred to T019
- **Why LIKE search?** → WHMCS 5.3.x uses simple LIKE queries; no Elasticsearch
- **Why published filter?** → Matches WHMCS behavior (only published content visible)

## Shadow Docs Banked

1. **SD-DOMAIN-008** — WHMCS knowledgebase structure: categories + articles with published flag

## Next Steps

T011: Affiliates module — referral tracking and commission management.