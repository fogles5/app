'use client';

import { useActionState, useState } from 'react';
import AiBioWriter from './AiBioWriter';
import { saveProfile } from '@/app/profile-actions';

export interface ProfileInit {
  display_name: string; about_me: string; height: number | null; body_type: string | null; ethnicity: string;
  relationship_status: string | null; interests: string; occupation: string; education: string | null;
  smoking: string | null; drinking: string | null; languages: string;
  display_distance: boolean; show_age: boolean; show_online_status: boolean; has_avatar: boolean;
}

const pretty = (v: string) => v.replace(/_/g, ' ').replace(/^./, (c) => c.toUpperCase());

function Select({ name, value, options }: { name: string; value: string | null; options: string[] }) {
  return (
    <select name={name} defaultValue={value ?? ''}>
      <option value="">—</option>
      {options.map((o) => <option key={o} value={o}>{pretty(o)}</option>)}
    </select>
  );
}

export default function ProfileForm({ init, aiEnabled = false }: { init: ProfileInit; aiEnabled?: boolean }) {
  const [state, action, pending] = useActionState(saveProfile, {});
  const [about, setAbout] = useState(init.about_me);
  return (
    <form action={action} className="form-card">
      {state.error && <div className="alert alert-danger" role="alert">{state.error}</div>}
      {state.ok && <div className="alert alert-ok" role="status">{state.ok}</div>}

      <label>Display name<input name="display_name" maxLength={100} defaultValue={init.display_name} /></label>
      <label>About me<textarea name="about_me" rows={5} maxLength={2000} value={about} onChange={(e) => setAbout(e.target.value)} /></label>
      <AiBioWriter enabled={aiEnabled} onResult={setAbout} />
      <label>Profile photo<input type="file" name="avatar" accept="image/jpeg,image/png,image/webp" /></label>
      {init.has_avatar && <label className="check"><input type="checkbox" name="remove_avatar" /> Remove current photo</label>}

      <div className="two-col">
        <label>Height (cm)<input type="number" name="height" min={100} max={250} defaultValue={init.height ?? ''} /></label>
        <label>Body type<Select name="body_type" value={init.body_type} options={['slim', 'athletic', 'average', 'curvy', 'muscular', 'heavyset', 'other']} /></label>
        <label>Relationship<Select name="relationship_status" value={init.relationship_status} options={['single', 'married', 'divorced', 'widowed', 'separated', 'in_relationship', 'complicated']} /></label>
        <label>Education<Select name="education" value={init.education} options={['high_school', 'some_college', 'bachelors', 'masters', 'phd', 'other']} /></label>
        <label>Smoking<Select name="smoking" value={init.smoking} options={['never', 'occasionally', 'regularly', 'trying_to_quit']} /></label>
        <label>Drinking<Select name="drinking" value={init.drinking} options={['never', 'socially', 'regularly']} /></label>
        <label>Occupation<input name="occupation" maxLength={100} defaultValue={init.occupation} /></label>
        <label>Ethnicity<input name="ethnicity" maxLength={100} defaultValue={init.ethnicity} /></label>
      </div>
      <label>Interests (comma separated)<input name="interests" maxLength={500} defaultValue={init.interests} /></label>
      <label>Languages (comma separated)<input name="languages" maxLength={300} defaultValue={init.languages} /></label>

      <label className="check"><input type="checkbox" name="show_age" defaultChecked={init.show_age} /> Show my age</label>
      <label className="check"><input type="checkbox" name="show_online_status" defaultChecked={init.show_online_status} /> Show online status</label>
      <label className="check"><input type="checkbox" name="display_distance" defaultChecked={init.display_distance} /> Show distance</label>

      <button className="btn btn-primary" type="submit" disabled={pending}>{pending ? 'Saving…' : 'Save profile'}</button>
    </form>
  );
}
