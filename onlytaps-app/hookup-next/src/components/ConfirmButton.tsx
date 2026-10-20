'use client';

export default function ConfirmButton({ message, children }: { message: string; children: React.ReactNode }) {
  return (
    <button className="btn btn-secondary" type="submit" onClick={(e) => { if (!confirm(message)) e.preventDefault(); }}>
      {children}
    </button>
  );
}
