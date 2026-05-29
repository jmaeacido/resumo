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
                count($matchedSkills) ? 'The resume directly matches several target skills, including ' . implode(', ', array_slice($matchedSkills, 0, 8)) . ', which helps both ATS matching and recruiter confidence.' : 'The resume includes transferable technical and operational skills, but direct matches against the provided job skill language are limited.',
                $base['scores']['ATS Readability'] >= 75 ? 'The resume structure is reasonably ATS-friendly, with standard section signals that should help parsing systems locate experience, education, skills, and contact details.' : 'The resume contains analyzable content, but its structure may need simpler headings, cleaner bullets, and fewer visual elements for stronger ATS parsing.',
                $scores['Experience Relevance'] >= 70 ? 'The experience language aligns with role responsibilities, especially where it describes systems, reporting workflows, deployment support, troubleshooting, and process improvements.' : 'Some experience is relevant to the target role, but the strongest role-specific evidence should be moved higher and written with clearer outcomes.',
                'The resume includes concrete project and operations context, which gives recruiters more evidence than a skills-only profile and supports stronger role alignment.',
            ],
            'weaknesses' => [
                count($missingSkills) ? 'The resume does not clearly show several target skills: ' . implode(', ', array_slice($missingSkills, 0, 8)) . '. Add only the ones you can support with honest evidence.' : 'No major missing skills were found from the built-in skill bank, but the resume should still mirror the employer wording where it is accurate.',
                $scores['Keyword Match'] < 70 ? 'Important job-description keywords are underrepresented, which may reduce ranking in ATS searches even when the underlying experience is relevant.' : 'Keyword coverage is healthy overall, but a few priority terms could still be placed in the summary, skills section, or most relevant project bullets.',
                $scores['Tailoring Quality'] < 70 ? 'The resume does not yet read as customized for this specific role because the opening summary and recent experience do not consistently echo the job language.' : 'Tailoring is visible, but it could be sharpened by connecting each major project or responsibility to the employer’s stated priorities.',
                'Some accomplishments may still read as task descriptions, so the application could be more competitive with clearer evidence of scale, ownership, and measurable impact.',
            ],
            'keywords' => array_slice(array_values(array_unique(array_merge($missingSkills, $missingKeywords))), 0, 18),
            'recommendations' => [
                count($missingSkills) ? 'Add honest evidence for ' . implode(', ', array_slice($missingSkills, 0, 5)) . ' where applicable, preferably inside project or experience bullets rather than as unsupported keywords.' : 'Keep the skills section close to the job requirements without stuffing keywords, and prioritize the tools most important to the employer.',
                'Mirror the job title and priority responsibilities in the summary and recent experience sections so the first screen immediately signals role fit.',
                'Add measurable results to the most relevant projects or roles, such as users supported, reports automated, deployment time reduced, incidents resolved, or workflow hours saved.',
                'Group technical skills so required tools are easy for recruiters and ATS systems to find, with the most job-relevant stack appearing first.',
                'Rewrite the top three role-matching bullets to include the employer’s language, the technical action you performed, and the result that proves capability.',
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
        if ($scores['ATS Readability'] >= 80) $items[] = 'The resume uses recognizable section labels and plain text structure, which should help ATS systems identify contact details, experience, education, skills, and projects reliably.';
        if ($sections['skills'] && count($skills) >= 5) $items[] = 'The skills section contains several concrete technical keywords, making it easier for recruiters and screening tools to connect the profile with web development and support roles.';
        if ($numbers >= 4) $items[] = 'The resume includes numeric evidence in multiple places, which can make accomplishments feel more credible when tied directly to outcomes, scale, uptime, savings, or delivery speed.';
        if ($scores['Grammar & Tone'] >= 85) $items[] = 'The writing is generally professional and readable, with a tone that fits technical support, development, documentation, and operational coordination responsibilities.';
        if ($sections['projects']) $items[] = 'The projects section gives useful proof of practical system-building experience, especially around authentication, reporting dashboards, Linux deployment, notifications, and workflow automation.';
        $items[] = 'The resume presents a coherent technical direction, combining PHP/MySQL development, Linux deployment, reporting systems, troubleshooting, and documentation into a consistent web systems profile.';
        return $items ?: ['The resume contains enough structured content to produce a meaningful baseline review, including role history, skills, and education signals recruiters can evaluate.'];
    }

    private static function resumeWeaknesses(array $scores, array $sections, int $numbers): array
    {
        $missing = array_filter(array_keys($sections), fn ($key) => !$sections[$key] && $key !== 'awards');
        $items = [];
        if ($missing) $items[] = 'Some standard resume sections are missing or unclear: ' . implode(', ', array_slice($missing, 0, 5)) . '. This can reduce confidence for recruiters who scan for credentials, certifications, or role evidence quickly.';
        if ($numbers < 3) $items[] = 'The experience descriptions have limited measurable impact, so the reader may understand the responsibilities but not the scale, difficulty, or business value of the work.';
        if ($scores['Content Quality'] < 72) $items[] = 'Some content may read like responsibility statements instead of achievement statements, which makes it harder to distinguish individual contribution from routine participation.';
        if ($scores['Formatting'] < 72) $items[] = 'The formatting may be difficult for ATS parsing or quick recruiter review if the original file uses icons, dense lists, repeated content, or visual elements that extract poorly.';
        $items[] = 'Several bullets describe systems and workflows clearly, but they would be stronger if each one named the problem, technical action, and concrete result in one compact statement.';
        $items[] = 'The skills list is broad and useful, but grouping the most job-relevant tools first would help recruiters quickly see the strongest fit for a specific target role.';
        $items[] = 'Some content may be repeated or overly similar across sections, which can make the resume feel longer without adding new evidence of capability or impact.';
        return $items ?: ['No severe structural weakness was detected, but the resume can still be improved by sharpening outcomes, removing repeated lines, and making each section more evidence-driven.'];
    }

    private static function resumeRecommendations(array $scores, array $sections, int $numbers): array
    {
        $items = [];
        if (!$sections['summary']) $items[] = 'Add a concise professional summary that names the target role, strongest technical stack, years or depth of experience, and the business problems you can solve.';
        if (!$sections['skills']) $items[] = 'Add a dedicated skills section grouped by category, such as development, deployment, databases, support operations, reporting tools, and collaboration platforms.';
        if (!$sections['certifications']) $items[] = 'Add relevant certifications, trainings, or completed courses if available, especially in PHP, Laravel, Linux administration, cloud basics, cybersecurity, or technical support.';
        if ($numbers < 4) $items[] = 'Rewrite the strongest experience and project bullets to include metrics such as users supported, reports processed, downtime reduced, deployment frequency, turnaround time, or automation savings.';
        if ($scores['ATS Readability'] < 80) $items[] = 'Use standard headings, simple bullets, and text-based contact details while avoiding icons, tables, columns, and decorative elements that can turn into extraction artifacts.';
        $items[] = 'Start each major bullet with a strong action verb, then describe the technical action, the system or workflow affected, and the measurable or operational result.';
        $items[] = 'Prioritize the top third of the resume around the target role by placing the strongest PHP, MySQL, Linux, deployment, reporting, and support evidence before secondary tools.';
        $items[] = 'Review the final section for repeated statements and keep only the strongest version of each point so the resume feels concise and intentionally edited.';
        $items[] = 'For each major project, add a short result statement that explains who used the system, what improved, and why the work mattered operationally.';
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
