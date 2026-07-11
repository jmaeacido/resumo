<?php

namespace App\Services;

use Illuminate\Support\Str;

class CareerToolkitService
{
    public function diagnostics(string $resume, string $jobDescription = ''): array
    {
        $plain = trim(preg_replace('/\s+/', ' ', $resume) ?? '');
        $words = str_word_count($plain);
        preg_match_all('/\b\d+(?:[.,]\d+)?%?\b/', $plain, $numbers);
        $sections = ['summary', 'experience', 'education', 'skills'];
        $missingSections = array_values(array_filter($sections, fn ($section) => ! preg_match('/\b'.preg_quote($section, '/').'\b/i', $plain)));
        $jobKeywords = $this->keywords($jobDescription);
        $missingKeywords = array_values(array_filter($jobKeywords, fn ($keyword) => ! Str::contains(Str::lower($plain), $keyword)));
        $issues = [];

        if ($words < 250) $issues[] = 'Resume may be too brief to demonstrate enough evidence.';
        if ($words > 1100) $issues[] = 'Resume may be too long for a focused recruiter scan.';
        if (count($numbers[0]) < 3) $issues[] = 'Add more measurable outcomes, scale, percentages, or time saved.';
        if ($missingSections) $issues[] = 'Missing or nonstandard sections: '.implode(', ', $missingSections).'.';
        if (preg_match('/[│|]{2,}|\t{2,}/', $resume)) $issues[] = 'Columns or table-like formatting may reduce ATS parsing accuracy.';

        $score = max(0, min(100, 100 - count($issues) * 9 - min(30, count($missingKeywords) * 3)));

        return compact('score', 'words', 'missingSections', 'missingKeywords', 'issues');
    }

    public function suggestion(string $text, string $targetRole = ''): array
    {
        $clean = trim($text);
        $rewrite = preg_replace('/^(responsible for|worked on|helped with)\s+/i', '', $clean) ?: $clean;
        $rewrite = ucfirst(rtrim($rewrite, '.')).'.';
        if (! preg_match('/\b\d+(?:[.,]\d+)?%?\b/', $rewrite)) {
            $rewrite = rtrim($rewrite, '.').', improving [metric] by [amount].';
        }

        return [
            'id' => (string) Str::uuid(),
            'original' => $clean,
            'rewrite' => $rewrite,
            'reason' => 'Uses a stronger action-led structure and prompts for measurable impact'.($targetRole ? " for {$targetRole}" : '').'.',
            'status' => 'pending',
            'created_at' => now()->toIso8601String(),
        ];
    }

    public function coverLetter(string $resume, string $jobTitle, string $company, string $jobDescription): string
    {
        $keywords = array_slice($this->keywords($jobDescription), 0, 5);
        $strength = $keywords ? implode(', ', $keywords) : 'the role’s core requirements';

        return "Dear Hiring Team,\n\nI am applying for the {$jobTitle} opportunity at {$company}. My experience aligns with {$strength}, and I would welcome the opportunity to bring that background to your team.\n\n".
            "My resume demonstrates relevant hands-on work, continuous improvement, and a focus on outcomes. I am particularly interested in this role because it connects my experience with the priorities described in your posting.\n\n".
            "Thank you for considering my application. I would be glad to discuss how my experience can support {$company}.\n\nSincerely,\n".
            $this->candidateName($resume);
    }

    public function interviewKit(string $jobTitle, string $jobDescription): array
    {
        $keywords = array_slice($this->keywords($jobDescription), 0, 4);
        $questions = [
            "Tell me about yourself and why you are interested in the {$jobTitle} role.",
            'Describe a difficult problem you owned and how you measured the result.',
            'Tell me about a time you disagreed with a stakeholder and what happened.',
            'Which accomplishment best demonstrates your readiness for this position?',
        ];
        foreach ($keywords as $keyword) $questions[] = "Describe your practical experience with {$keyword}.";

        return [
            'id' => (string) Str::uuid(),
            'job_title' => $jobTitle,
            'questions' => $questions,
            'star_framework' => ['Situation: establish context', 'Task: clarify your responsibility', 'Action: explain your decisions', 'Result: quantify the outcome'],
            'questions_to_ask' => ['What would success look like after 90 days?', 'What are the team’s biggest current challenges?', 'How is performance measured for this role?'],
            'created_at' => now()->toIso8601String(),
        ];
    }

    public function linkedinReview(string $profile, string $targetRole): array
    {
        $issues = [];
        if (mb_strlen($profile) < 400) $issues[] = 'The profile needs more evidence and role-specific detail.';
        if (! preg_match('/\b\d+(?:[.,]\d+)?%?\b/', $profile)) $issues[] = 'Add measurable outcomes to the About or Experience sections.';
        if ($targetRole && ! Str::contains(Str::lower($profile), Str::lower($targetRole))) $issues[] = 'Include the target role naturally in the headline or About section.';

        return [
            'id' => (string) Str::uuid(),
            'target_role' => $targetRole,
            'score' => max(35, 95 - count($issues) * 18),
            'issues' => $issues,
            'headline' => trim($targetRole).' | Results-focused professional | Add your strongest specialty',
            'about_prompt' => 'Lead with your target role, years or depth of experience, strongest proof, core skills, and the opportunities you want.',
            'created_at' => now()->toIso8601String(),
        ];
    }

    private function keywords(string $text): array
    {
        $stop = array_flip(['and','the','with','for','that','this','from','your','you','our','are','will','have','has','job','role','work','team','skills','experience','years','using','who','but','not']);
        preg_match_all('/[a-z][a-z0-9+#.-]{2,}/i', Str::lower($text), $matches);
        $counts = array_count_values(array_filter($matches[0], fn ($word) => ! isset($stop[$word])));
        arsort($counts);
        return array_slice(array_keys($counts), 0, 12);
    }

    private function candidateName(string $resume): string
    {
        return trim(strtok(trim($resume), "\r\n")) ?: 'Your Name';
    }
}
