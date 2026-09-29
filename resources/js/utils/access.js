// Pimpinan (dan superadmin) melihat data semua unit, tapi hanya anggota unit itu
// (atau superadmin / PIC Task) yang boleh mengubahnya — cerminan TaskPolicy::update.
export const canEditTask = (user, task) => user.role === 'superadmin'
    || (task.unit_id != null && task.unit_id === user.unit_id)
    || (task.assignee_id != null && task.assignee_id === user.id);
