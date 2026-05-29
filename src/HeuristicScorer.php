<?php

namespace Resumo;

final class HeuristicScorer
{
    private const RESUME_WEIGHTS = [
        'ATS Readability' => 20,
        'Resume Completeness' => 15,
        'Content Quality' => 20,
        'Experience Impact' => 20,
        'Skills Clarity' => 10,
        'Grammar & Tone' => 10,
        'Formatting' => 5,
    ];

    private const JOB_WEIGHTS = [
        'Skills Match' => 30,
        'Experience Relevance' => 25,
        'Keyword Match' => 15,
        'ATS Readability' => 10,
        'Education & Certification' => 10,
        'Tailoring Quality' => 10,
    ];

    private const SKILLS = [
        'javascript', 'typescript', 'php', 'laravel', 'python', 'java', 'c#', 'sql', 'mysql', 'postgresql', 'mongodb',
        'html', 'css', 'react', 'vue', 'angular', 'node', 'express', 'api', 'rest', 'graphql', 'docker', 'kubernetes',
        'aws', 'azure', 'gcp', 'linux', 'git', 'ci/cd', 'devops', 'testing', 'qa', 'figma', 'analytics', 'excel',
        'power bi', 'tableau', 'seo', 'crm', 'salesforce', 'project management', 'agile', 'scrum', 'leadership'
    ];

    private const ACTION_VERBS = [
        'built', 'led', 'created', 'improved', 'reduced', 'increased', 'delivered', 'designed', 'managed', 'launched',
        'optimized', 'automated', 'implemented', 'developed', 'migrated', 'supported', 'streamlined', 'coordinated'
    ];

    private const VAGUE_TERMS = ['responsible for', 'hardworking', 'team player', 'detail-oriented', 'various tasks', 'helped with'];

    public static function analyze(string $resumeText, string $mode = 'resume', string $jobTitle = '', string $jobDescription = ''): array
    {
        $base = self::resumeOnly($resumeText);
        if ($mode === 'job' && trim($jobDescription) !== '') {
            return self::jobMatch($base, $resumeText, $jobTitle, $jobDescription);
        }

        return $base;
    }

    private static function resumeOnly(string $text): array
    {
        $lower = mb_strtolower($text);
        $sections = self::sections($text);
        $sectionCount = count(array_filter($sections));
        $bullets = preg_match_all('/(^|\n)\s*[-*•]/u', $text);
        $numbers = preg_match_all('/\b\d+[%+]?|\$[\d,.]+/u', $text);
        $words = preg_match_all('/\b[\pL\pN_]+\b/u', $text);
        $actions = self::countMatches($lower, self::ACTION_VERBS);
        $vague = self::countMatches($lower, self::VAGUE_TERMS);
        $skills = self::skills($text);
        $complex = preg_match('/\|.{2,}\||\t{2,}|_{4,}|={4,}/u', $text) === 1;

        $scores = [
            'ATS Readability' => self::clamp(68 + $sectionCount * 4 + ($bullets > 4 ? 8 : 0) - ($complex ? 15 : 0)),
            'Resume Completeness' => self::clamp(($sectionCount / 7) * 100),
            'Content Quality' => self::clamp(62 + $actions * 3 - $vague * 8 + ($words > 250 ? 10 : 0)),
            'Experience Impact' => self::clamp(48 + min($numbers * 8, 36) + min($actions * 2, 16)),
            'Skills Clarity' => self::clamp(45 + count($skills) * 8 + ($sections['skills'] ? 15 : 0)),
            'Grammar & Tone' => self::clamp(88 - preg_match_all('/ {3,}/', $text) * 4 - self::countMatches($lower, ['teh', 'recieve', 'managment', 'experiance']) * 7),
            'Formatting' => self::clamp(70 + min($bullets * 2, 16) + ($words <= 950 ? 8 : -12) - ($complex ? 14 : 0)),
        ];

        $overall = self::weighted($scores, self::RESUME_WEIGHTS);
        return [
            'mode' => 'resume',
            'job_title' => null,
            'title' => 'Overall Resume Score',
            'subtitle' => 'General resume quality assessment. This mode does not calculate job suitability.',
            'overall' => $overall,
            'scores' => $scores,
            'sections' => $sections,
            'strengths' => self::resumeStrengths($scores, $sections, $skills, $numbers),
            'weaknesses' => self::resumeWeaknesses($scores, $sections, $numbers),
            'keywords' => [],
            'recommendations' => self::resumeRecommendations($scores, $sections, $numbers),
            'metrics' => [
                ['ATS Rating', self::rating($scores['ATS Readability']), $scores['ATS Readability'] . '/100 parsing readiness'],
                ['Resume Status', self::status($overall), 'General quality classification'],
                ['Detected Sections', $sectionCount . '/8', implode(', ', array_keys(array_filter($sections))) ?: 'None detected'],
            ],
        ];
    }

    private static function jobMatch(array $base, string $resume, string $jobTitle, string $jobDescription): array
    {
        $resumeLower = mb_strtolower($resume);
        $jobSkills = self::skills($jobDescription);
        $resumeSkills = self::skills($resume);
        $matchedSkills = array_values(array_filter($jobSkills, fn ($skill) => str_contains($resumeLower, $skill)));
        $missingSkills = array_values(array_filter($jobSkills, fn ($skill) => !str_contains($resumeLower, $skill)));
        $jobKeywords = self::keywords($jobDescription);
        $matchedKeywords = array_values(array_filter($jobKeywords, fn ($word) => str_contains($resumeLower, $word)));
        $missingKeywords = array_slice(array_values(array_filter($jobKeywords, fn ($word) => !str_contains($resumeLower, $word))), 0, 14);
        $roleWords = array_slice(self::keywords($jobTitle . ' ' . $jobDescription), 0, 12);
        $roleHits = count(array_filter($roleWords, fn ($word) => str_contains($resumeLower, $word)));

        $scores = [
            'Skills Match' => count($jobSkills) ? self::clamp(count($matchedSkills) / count($jobSkills) * 100) : self::clamp(55 + count($resumeSkills) * 5),
            'Experience Relevance' => self::clamp(45 + $roleHits * 5 + self::countMatches($resumeLower, ['developed', 'managed', 'led', 'implemented', 'supported']) * 4),
            'Keyword Match' => count($jobKeywords) ? self::clamp(count($matchedKeywords) / count($jobKeywords) * 100) : 60,
            'ATS Readability' => $base['scores']['ATS Readability'],
            'Education & Certification' => self::clamp(50 + ($base['sections']['education'] ? 25 : 0) + ($base['sections']['certifications'] ? 20 : 0) + self::countMatches($resumeLower, ['degree', 'certified', 'certification']) * 5),
            'Tailoring Quality' => self::clamp(45 + count($matchedSkills) * 6 + $roleHits * 4 + ($jobTitle && str_contains($resumeLower, mb_strtolower($jobTitle)) ? 10 : 0)),
        ];

        $overall = self::weighted($scores, self::JOB_WEIGHTS);
        return [
            'mode' => 'job',
            'job_title' => $jobTitle ?: 'Target role',
            'title' => 'Job Match Score',
            'subtitle' => 'Resume alignment for ' . ($jobTitle ?: 'the target role') . '.',
            'overall' => $overall,
            'scores' => $scores,
            'sections' => $base['sections'],
            'strengths' => [
                count($matchedSkills) ? 'Matched skills: ' . implode(', ', array_slice($matchedSkills, 0, 8)) . '.' : 'Resume includes transferable skills, but direct job skill matches are limited.',
                $base['scores']['ATS Readability'] >= 75 ? 'Resume structure is reasonably ATS-friendly.' : 'Resume content is available for analysis, but ATS structure needs work.',
                $scores['Experience Relevance'] >= 70 ? 'Experience language aligns with role responsibilities.' : 'Some experience is relevant, but role-specific evidence is thin.',
            ],
            'weaknesses' => [
                count($missingSkills) ? 'Missing target skills: ' . implode(', ', array_slice($missingSkills, 0, 8)) . '.' : 'No major missing skills found from the built-in skill bank.',
                $scores['Keyword Match'] < 70 ? 'Important job-description keywords are underrepresented.' : 'Keyword coverage is healthy, with a few optimization opportunities.',
                $scores['Tailoring Quality'] < 70 ? 'The resume does not yet read as customized for this specific role.' : 'Tailoring is visible, but could be sharpened with more role language.',
            ],
            'keywords' => array_slice(array_values(array_unique(array_merge($missingSkills, $missingKeywords))), 0, 18),
            'recommendations' => [
                count($missingSkills) ? 'Add honest evidence for ' . implode(', ', array_slice($missingSkills, 0, 5)) . ' where applicable.' : 'Keep the skills section close to the job requirements without stuffing keywords.',
                'Mirror the job title and priority responsibilities in the summary and recent experience sections.',
                'Add measurable results to the most relevant projects or roles.',
                'Group technical skills so required tools are easy for recruiters and ATS systems to find.',
            ],
            'metrics' => [
                ['Job Match Rating', self::rating($overall), $overall . '/100 alignment'],
                ['Matched Skills', count($matchedSkills) . '/' . max(count($jobSkills), 1), implode(', ', $matchedSkills) ?: 'No direct skill matches detected'],
                ['Missing Keywords', (string)(count($missingKeywords) + count($missingSkills)), 'Priority terms to consider adding honestly'],
            ],
        ];
    }

    private static function sections(string $text): array
    {
        return [
            'contact' => preg_match('/(\b[\w.%+-]+@[\w.-]+\.[a-z]{2,}\b)|(\+?\d[\d\s().-]{7,})|(linkedin\.com\/in\/)/i', $text) === 1,
            'summary' => preg_match('/\b(summary|profile|objective|professional summary|career summary)\b/i', $text) === 1,
            'experience' => preg_match('/\b(experience|employment|work history|professional experience)\b/i', $text) === 1,
            'education' => preg_match('/\b(education|degree|university|college|bachelor|master|phd|diploma)\b/i', $text) === 1,
            'skills' => preg_match('/\b(skills|technical skills|core competencies|technologies)\b/i', $text) === 1,
            'certifications' => preg_match('/\b(certifications?|licenses?|accreditations?)\b/i', $text) === 1,
            'projects' => preg_match('/\b(projects?|portfolio|selected work)\b/i', $text) === 1,
            'awards' => preg_match('/\b(awards?|honors?|achievements?)\b/i', $text) === 1,
        ];
    }

    private static function skills(string $text): array
    {
        $lower = mb_strtolower($text);
        return array_values(array_filter(self::SKILLS, fn ($skill) => str_contains($lower, $skill)));
    }

    private static function keywords(string $text): array
    {
        $stop = array_flip(['and', 'the', 'with', 'for', 'you', 'our', 'are', 'that', 'this', 'will', 'from', 'have', 'has', 'into', 'your', 'their', 'about', 'using']);
        preg_match_all('/[a-z][a-z+#.\/-]{2,}/i', mb_strtolower($text), $matches);
        $counts = [];
        foreach ($matches[0] as $word) {
            if (!isset($stop[$word])) {
                $counts[$word] = ($counts[$word] ?? 0) + 1;
            }
        }
        arsort($counts);
        return array_slice(array_keys($counts), 0, 35);
    }

    private static function countMatches(string $text, array $terms): int
    {
        return count(array_filter($terms, fn ($term) => str_contains($text, $term)));
    }

    private static function resumeStrengths(array $scores, array $sections, array $skills, int $numbers): array
    {
        $items = [];
        if ($scores['ATS Readability'] >= 80) $items[] = 'Clear structure with ATS-friendly section signals.';
        if ($sections['skills'] && count($skills) >= 5) $items[] = 'Visible skills section with relevant technical or professional keywords.';
        if ($numbers >= 4) $items[] = 'Resume includes measurable achievements and numeric evidence.';
        if ($scores['Grammar & Tone'] >= 85) $items[] = 'Professional tone with few obvious consistency issues.';
        if ($sections['projects']) $items[] = 'Project section helps demonstrate practical experience.';
        return $items ?: ['Resume has enough text to produce a baseline quality review.'];
    }

    private static function resumeWeaknesses(array $scores, array $sections, int $numbers): array
    {
        $missing = array_filter(array_keys($sections), fn ($key) => !$sections[$key] && $key !== 'awards');
        $items = [];
        if ($missing) $items[] = 'Missing or unclear sections: ' . implode(', ', array_slice($missing, 0, 5)) . '.';
        if ($numbers < 3) $items[] = 'Limited measurable accomplishments or business impact.';
        if ($scores['Content Quality'] < 72) $items[] = 'Content may rely on generic language instead of action-oriented evidence.';
        if ($scores['Formatting'] < 72) $items[] = 'Formatting may be too complex, too long, or inconsistent.';
        return $items ?: ['No major weaknesses detected in the general resume review.'];
    }

    private static function resumeRecommendations(array $scores, array $sections, int $numbers): array
    {
        $items = [];
        if (!$sections['summary']) $items[] = 'Add a concise professional summary tailored to your target role.';
        if (!$sections['skills']) $items[] = 'Add a dedicated skills section grouped by category.';
        if ($numbers < 4) $items[] = 'Rewrite experience bullets to include metrics, scale, revenue, time saved, uptime, or user impact.';
        if ($scores['ATS Readability'] < 80) $items[] = 'Use standard headings and avoid tables, images, columns, and heavy visual formatting.';
        $items[] = 'Start bullets with strong action verbs and focus each one on a contribution or result.';
        return $items;
    }

    private static function weighted(array $scores, array $weights): int
    {
        $total = array_sum($weights);
        $score = 0;
        foreach ($weights as $name => $weight) {
            $score += ($scores[$name] ?? 0) * $weight;
        }
        return self::clamp($score / $total);
    }

    private static function rating(int $score): string
    {
        return $score >= 85 ? 'Excellent' : ($score >= 75 ? 'Strong' : ($score >= 65 ? 'Fair' : 'Needs Work'));
    }

    private static function status(int $score): string
    {
        return $score >= 85 ? 'Interview Ready' : ($score >= 75 ? 'Competitive' : ($score >= 65 ? 'Needs Refinement' : 'Needs Revision'));
    }

    private static function clamp(float|int $value): int
    {
        return max(0, min(100, (int)round($value)));
    }
}
