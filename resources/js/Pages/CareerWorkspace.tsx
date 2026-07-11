import ResumoLayout from '@/Layouts/ResumoLayout';
import { Head } from '@inertiajs/react';
import { useMemo, useState } from 'react';

type Item = Record<string, any>;
type Workspace = {
    base_resume?: string;
    resume_versions?: Item[];
    applications?: Item[];
    cover_letters?: Item[];
    suggestions?: Item[];
    interview_kits?: Item[];
    linkedin_reviews?: Item[];
    usage?: Record<string, any>;
};

const tabs = ['Resume', 'Versions', 'Jobs', 'Cover letters', 'Suggestions', 'Interview', 'LinkedIn', 'Operations'];
const statuses = ['saved', 'preparing', 'applied', 'interview', 'offer', 'rejected'];

export default function CareerWorkspace({ workspace: initial, operations }: { workspace: Workspace; operations: Item }) {
    const [workspace, setWorkspace] = useState(initial);
    const [tab, setTab] = useState('Resume');
    const [busy, setBusy] = useState(false);
    const [message, setMessage] = useState('');
    const [form, setForm] = useState<Record<string, string>>({ content: initial.base_resume ?? '' });

    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
    const act = async (action: string, payload: Item = form) => {
        setBusy(true); setMessage('');
        try {
            const response = await fetch(route('workspace.update'), { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf }, body: JSON.stringify({ action, payload }) });
            const data = await response.json();
            if (!response.ok) throw new Error(data.message ?? 'Action failed');
            setWorkspace(data.workspace); setMessage('Saved successfully.');
        } catch (error) { setMessage(error instanceof Error ? error.message : 'Action failed'); }
        finally { setBusy(false); }
    };
    const field = (name: string, placeholder: string, multiline = false) => multiline ? (
        <textarea value={form[name] ?? ''} onChange={(e) => setForm({ ...form, [name]: e.target.value })} placeholder={placeholder} className="min-h-28 w-full rounded-lg border-slate-300 text-sm" />
    ) : (
        <input value={form[name] ?? ''} onChange={(e) => setForm({ ...form, [name]: e.target.value })} placeholder={placeholder} className="w-full rounded-lg border-slate-300 text-sm" />
    );
    const counts = useMemo(() => ({ versions: workspace.resume_versions?.length ?? 0, jobs: workspace.applications?.length ?? 0, letters: workspace.cover_letters?.length ?? 0 }), [workspace]);
    const scoreDelta = useMemo(() => {
        const versions = workspace.resume_versions ?? [];
        if (versions.length < 2) return null;
        return (versions[versions.length - 1].diagnostics?.score ?? 0) - (versions[0].diagnostics?.score ?? 0);
    }, [workspace.resume_versions]);

    return <ResumoLayout header={<div><h1 className="text-2xl font-bold text-slate-900">Career workspace</h1><p className="text-sm text-slate-600">Build, tailor, track, and prepare every application in one place.</p></div>}>
        <Head title="Career Workspace" />
        <div className="mb-5 grid gap-3 sm:grid-cols-4"><Stat label="Resume versions" value={counts.versions}/><Stat label="Tracked jobs" value={counts.jobs}/><Stat label="Cover letters" value={counts.letters}/><Stat label="Score improvement" value={scoreDelta ?? 0} suffix={scoreDelta === null ? '' : ' pts'}/></div>
        <div className="mb-6 flex gap-2 overflow-x-auto pb-2">{tabs.map((item) => <button key={item} onClick={() => setTab(item)} className={`whitespace-nowrap rounded-full px-4 py-2 text-sm ${tab === item ? 'bg-resumo-700 text-white' : 'bg-white text-slate-700 ring-1 ring-slate-200'}`}>{item}</button>)}</div>
        {message && <div className="mb-4 rounded-lg bg-resumo-50 px-4 py-3 text-sm text-resumo-800">{message}</div>}
        <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            {tab === 'Resume' && <><Title title="Editable base resume" text="This is the source used by the tailoring tools."/>{field('content', 'Paste your full resume', true)}<Action busy={busy} onClick={() => act('save_base')}>Save base resume</Action></>}
            {tab === 'Versions' && <><Title title="Tailored resume versions" text="Create a version and capture ATS diagnostics for a target job."/><div className="grid gap-3 md:grid-cols-2">{field('name','Version name')}{field('job_title','Target role')}{field('job_description','Job description',true)}{field('version_content','Resume content (optional)',true)}</div><Action busy={busy} onClick={() => act('create_version', { ...form, content: form.version_content || workspace.base_resume || '' })}>Create scored version</Action><Cards items={workspace.resume_versions} render={(x) => <><b>{x.name}</b><p>{x.job_title}</p><p className="mt-2 text-resumo-700">ATS score: {x.diagnostics?.score}</p><p>{x.diagnostics?.issues?.join(' ')}</p></>}/></>}
            {tab === 'Jobs' && <><Title title="Application tracker" text="Move each opportunity from saved through offer."/><div className="grid gap-3 md:grid-cols-2">{field('company','Company')}{field('job_title','Job title')}{field('description','Job description',true)}</div><Action busy={busy} onClick={() => act('save_application')}>Add job</Action><div className="mt-6 grid gap-4 lg:grid-cols-3">{statuses.map((status) => <div key={status} className="rounded-xl bg-slate-50 p-3"><h3 className="mb-3 font-semibold capitalize">{status}</h3>{workspace.applications?.filter((x) => x.status === status).map((x) => <div key={x.id} className="mb-2 rounded-lg bg-white p-3 text-sm shadow-sm"><b>{x.job_title}</b><p>{x.company}</p><select value={x.status} onChange={(e) => act('move_application',{ id:x.id,status:e.target.value })} className="mt-2 w-full rounded border-slate-200 text-xs">{statuses.map(s=><option key={s}>{s}</option>)}</select></div>)}</div>)}</div></>}
            {tab === 'Cover letters' && <><Title title="Tailored cover letters" text="Generate a grounded draft from your resume and a specific job."/><div className="grid gap-3 md:grid-cols-2">{field('company','Company')}{field('job_title','Job title')}{field('job_description','Job description',true)}</div><Action busy={busy} onClick={() => act('generate_cover_letter')}>Generate letter</Action><Cards items={workspace.cover_letters} render={(x) => <><b>{x.job_title} — {x.company}</b><pre className="mt-3 whitespace-pre-wrap font-sans">{x.content}</pre></>}/></>}
            {tab === 'Suggestions' && <><Title title="Accept or reject rewrites" text="Turn weak duties into action-led, measurable bullets."/>{field('text','Paste a resume bullet',true)}{field('target_role','Target role')}<Action busy={busy} onClick={() => act('create_suggestion')}>Suggest rewrite</Action><Cards items={workspace.suggestions} render={(x) => <><p className="line-through opacity-60">{x.original}</p><p className="mt-2 font-medium">{x.rewrite}</p><p className="mt-1 text-xs">{x.reason}</p><div className="mt-3 flex gap-2"><button onClick={()=>act('suggestion_status',{id:x.id,status:'accepted'})} className="text-emerald-700">Accept</button><button onClick={()=>act('suggestion_status',{id:x.id,status:'rejected'})} className="text-rose-700">Reject</button><span className="ml-auto capitalize">{x.status}</span></div></>}/></>}
            {tab === 'Interview' && <><Title title="Interview preparation" text="Generate role-specific questions and STAR guidance."/>{field('job_title','Job title')}{field('job_description','Job description',true)}<Action busy={busy} onClick={() => act('generate_interview')}>Create interview kit</Action><Cards items={workspace.interview_kits} render={(x) => <><b>{x.job_title}</b><ul className="mt-2 list-disc pl-5">{x.questions?.map((q:string)=><li key={q}>{q}</li>)}</ul><p className="mt-3 font-medium">Questions to ask</p><ul className="list-disc pl-5">{x.questions_to_ask?.map((q:string)=><li key={q}>{q}</li>)}</ul></>}/></>}
            {tab === 'LinkedIn' && <><Title title="LinkedIn profile review" text="Review your headline, About section, evidence, and target-role alignment."/>{field('target_role','Target role')}{field('profile','Paste LinkedIn profile text',true)}<Action busy={busy} onClick={() => act('review_linkedin')}>Review profile</Action><Cards items={workspace.linkedin_reviews} render={(x) => <><b>Score: {x.score}</b><p className="mt-2">Suggested headline: {x.headline}</p><ul className="mt-2 list-disc pl-5">{x.issues?.map((q:string)=><li key={q}>{q}</li>)}</ul></>}/></>}
            {tab === 'Operations' && <><Title title="Privacy, usage, and operations" text="Export your data and review the services protecting this workspace."/><dl className="grid gap-3 sm:grid-cols-2"><Info label="Mail" value={operations.mail}/><Info label="Queue" value={operations.queue}/><Info label="Retention target" value={`${operations.retention_days} days`}/><Info label="Last export" value={operations.exported_at ?? 'Never'}/></dl><a href={route('workspace.export')} className="mt-5 inline-block rounded-lg bg-resumo-700 px-4 py-2 text-sm font-medium text-white">Export all workspace data</a><h3 className="mt-6 font-semibold">Usage</h3><pre className="mt-2 overflow-auto rounded-lg bg-slate-950 p-4 text-xs text-slate-100">{JSON.stringify(workspace.usage ?? {}, null, 2)}</pre></>}
        </section>
    </ResumoLayout>;
}

function Title({title,text}:{title:string;text:string}) { return <div className="mb-4"><h2 className="text-lg font-semibold text-slate-900">{title}</h2><p className="text-sm text-slate-600">{text}</p></div>; }
function Action({busy,onClick,children}:{busy:boolean;onClick:()=>void;children:any}) { return <button disabled={busy} onClick={onClick} className="mt-4 rounded-lg bg-resumo-700 px-4 py-2 text-sm font-medium text-white disabled:opacity-50">{busy?'Working…':children}</button>; }
function Cards({items=[],render}:{items?:Item[];render:(x:Item)=>any}) { return <div className="mt-6 grid gap-3 md:grid-cols-2">{items.slice().reverse().map((x)=><article key={x.id} className="rounded-xl border border-slate-200 p-4 text-sm text-slate-700">{render(x)}</article>)}</div>; }
function Stat({label,value,suffix=''}:{label:string;value:number;suffix?:string}) { return <div className="rounded-xl bg-white p-4 shadow-sm"><p className="text-sm text-slate-500">{label}</p><p className="text-2xl font-bold text-resumo-800">{value}{suffix}</p></div>; }
function Info({label,value}:{label:string;value:string}) { return <div className="rounded-lg bg-slate-50 p-3"><dt className="text-xs uppercase text-slate-500">{label}</dt><dd className="font-medium">{value}</dd></div>; }
