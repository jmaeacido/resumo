export interface AnalysisReport {
    id: number;
    mode: 'resume' | 'job';
    title: string;
    subtitle: string;
    overall: number;
    scores: Record<string, number>;
    sections: Record<string, boolean>;
    strengths: string[];
    weaknesses: string[];
    recommendations: string[];
    keywords: string[];
    recommended_resume?: RecommendedResume;
    metrics: [string, string, string][];
    engine: string;
    ai_status?: string;
    job_title?: string | null;
    resume_text?: string;
    resume_excerpt?: string;
    created_at?: string;
    access_token?: string | null;
}

export interface RecommendedResume {
    candidate_name?: string;
    contact?: string[];
    headline?: string;
    target_role?: string;
    summary?: string;
    sections?: { heading: string; items: string[] }[];
}

export interface ReportSummary {
    id: number;
    mode: string;
    score: number;
    job_title?: string | null;
    created_at?: string;
}

export interface ButlerContext {
    mode: string;
    has_resume_text: boolean;
    has_resume_file: boolean;
    has_job_description: boolean;
    has_report: boolean;
    has_recommended_resume: boolean;
    overall_score: number | null;
    authenticated: boolean;
}
