'use client';

import { useActionState, useMemo, useState } from 'react';
import { createListing, updateListing } from '@/app/listing-actions';

export interface StateOpt { id: number; name: string; abbr: string; cities: { id: number; name: string }[] }
export interface Initial {
  id?: number; category_id?: number; city_id?: number; title?: string; description?: string;
  age?: number | null; gender?: string | null; seeking?: string | null; neighborhood?: string | null; zip_code?: string | null;
}

export default function ListingForm(props: {
  mode: 'create' | 'edit'; categories: { id: number; name: string }[]; states: StateOpt[]; initial?: Initial;
}) {
  const { mode, categories, states, initial = {} } = props;
  const [state, action, pending] = useActionState(mode === 'create' ? createListing : updateListing, {});
  const startState = useMemo(
    () => states.find((s) => s.cities.some((c) => c.id === initial.city_id))?.id ?? states.find((s) => s.cities.length)?.id ?? 0,
    [states, initial.city_id]);
  const [stateId, setStateId] = useState(startState);
  const cities = states.find((s) => s.id === stateId)?.cities ?? [];
  const [desc, setDesc] = useState(initial.description ?? '');
  const [title, setTitle] = useState(initial.title ?? '');

  return (
    <form action={action} className="form-card">
      {state.error && <div className="alert alert-danger" role="alert">{state.error}</div>}
      {mode === 'edit' && <input type="hidden" name="id" value={initial.id} />}

      <label>Category
        <select name="category_id" required defaultValue={initial.category_id ?? ''}>
          <option value="" disabled>Select a category</option>
          {categories.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
        </select>
      </label>

      <div className="two-col">
        <label>State
          <select value={stateId} onChange={(e) => setStateId(Number(e.target.value))}>
            {states.filter((s) => s.cities.length).map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}
          </select>
        </label>
        <label>City
          <select name="city_id" required key={stateId} defaultValue={cities.some((c) => c.id === initial.city_id) ? initial.city_id : cities[0]?.id}>
            {cities.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
          </select>
        </label>
      </div>

      <label>Title <small className="muted">{title.length}/100</small>
        <input name="title" required minLength={10} maxLength={100} value={title} onChange={(e) => setTitle(e.target.value)} placeholder="A clear, descriptive title" />
      </label>

      <label>Description <small className="muted">{desc.length} chars (min 50)</small>
        <textarea name="description" required minLength={50} rows={8} value={desc} onChange={(e) => setDesc(e.target.value)} placeholder="Tell people about yourself and what you're looking for" />
      </label>

      <div className="two-col">
        <label>Age <input name="age" type="number" min={18} max={99} defaultValue={initial.age ?? ''} /></label>
        <label>Gender
          <select name="gender" defaultValue={initial.gender ?? ''}>
            <option value="">—</option>
            {['male', 'female', 'couple', 'trans', 'other'].map((g) => <option key={g} value={g}>{g}</option>)}
          </select>
        </label>
        <label>Seeking
          <select name="seeking" defaultValue={initial.seeking ?? ''}>
            <option value="">—</option>
            {['male', 'female', 'couple', 'trans', 'any'].map((g) => <option key={g} value={g}>{g}</option>)}
          </select>
        </label>
      </div>

      <div className="two-col">
        <label>Neighborhood <input name="neighborhood" maxLength={255} defaultValue={initial.neighborhood ?? ''} /></label>
        <label>ZIP code <input name="zip_code" maxLength={20} defaultValue={initial.zip_code ?? ''} /></label>
      </div>

      <label>Photo {mode === 'edit' && <small className="muted">(leave empty to keep current)</small>}
        <input name="photo" type="file" accept="image/jpeg,image/png,image/gif,image/webp" />
      </label>

      <button className="btn btn-primary" type="submit" disabled={pending}>
        {pending ? 'Saving…' : mode === 'create' ? 'Post ad' : 'Save changes'}
      </button>
    </form>
  );
}
