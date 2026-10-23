export type PublicFaqItem = {
    id: string;
    slug: string;
    category: string;
    question: string;
    answer: string;
    answer_excerpt?: string;
    office_code: string;
    office_label: string;
    governing_policy: { title: string; slug: string; revision_label: string } | null;
    updated_at: string;
};

export type PublicFaqCategory = { id: string; label: string };

export function filterFaqs(
    faqs: PublicFaqItem[],
    search: string,
    category: string,
): PublicFaqItem[] {
    const terms = search.trim().toLocaleLowerCase().split(/\s+/).filter(Boolean);
    const selectedCategory = category.toLocaleLowerCase();
    return faqs.filter((faq) => {
        const faqCategory = faq.category.toLocaleLowerCase();
        const categoryMatches = selectedCategory === '' || selectedCategory === 'all'
            || faqCategory === selectedCategory
            || faqCategory.includes(selectedCategory);
        const text = `${faq.question} ${faq.answer}`.toLocaleLowerCase();
        const searchMatches = terms.length === 0 || terms.every((term) => text.includes(term));
        return categoryMatches && searchMatches;
    });
}

export function matchFaqDeflection(faqs: PublicFaqItem[], subject: string): PublicFaqItem | null {
    const normalizedSubject = subject.trim().replace(/\s+/g, ' ').toLocaleLowerCase();
    if (normalizedSubject.length <= 3) return null;

    const subjectWords = new Set(normalizedSubject.split(/[^\p{L}\p{N}]+/u).filter((word) => word.length > 2));
    let bestMatch: PublicFaqItem | null = null;
    let bestScore = 0;
    for (const faq of faqs) {
        const question = faq.question.trim().replace(/\s+/g, ' ').toLocaleLowerCase();
        if (!question) continue;
        if (normalizedSubject.includes(question) || question.includes(normalizedSubject)) return faq;
        const questionWords = question.split(/[^\p{L}\p{N}]+/u).filter((word) => word.length > 2);
        if (questionWords.length === 0 || subjectWords.size === 0) continue;
        const matches = questionWords.filter((word) => subjectWords.has(word)).length;
        const score = matches / Math.min(questionWords.length, subjectWords.size);
        if (matches > 0 && score > bestScore) {
            bestMatch = faq;
            bestScore = score;
        }
    }
    return bestScore >= 0.3 ? bestMatch : null;
}
