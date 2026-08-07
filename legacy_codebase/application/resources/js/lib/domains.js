/**
 * Legacy/fallback aptitude areas (subjects).
 * The source of truth is the backend: aptitude_areas table, managed at Admin → Aptitude Areas.
 * Grading, consultation rules, and result templates receive areas from the API.
 * Use this constant only when backend areas are not available (e.g. static fallback).
 */
export const APTITUDE_AREAS = [
  { id: 1, name: 'Spatial Awareness',          code: 'SA',  max_score: 25 },
  { id: 2, name: 'Numerical Ability',           code: 'NA',  max_score: 25 },
  { id: 3, name: 'Verbal Reasoning',            code: 'VR',  max_score: 25 },
  { id: 4, name: 'Abstract Reasoning',          code: 'AR',  max_score: 20 },
  { id: 5, name: 'Logical Reasoning',           code: 'LR',  max_score: 25 },
  { id: 6, name: 'Perceptual Speed & Accuracy', code: 'PSA', max_score: 20 },
];
