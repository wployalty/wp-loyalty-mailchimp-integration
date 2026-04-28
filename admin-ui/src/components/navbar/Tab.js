import React from "react";
import {Link, useLocation} from "react-router-dom";

const Tab = ({tab}) => {
    const currentPath = useLocation().pathname.split("/")[1];
    return <Link to={tab.path}
                 className={`wlmi-relative  wlmi-shadow-none focus:wlmi-outline-none  wlmi-transition wlmi-duration-200 wlmi-ease-in`}
    >
        <p className={`wlmi-py-3 wlmi-px-6 2xl:wlmi-text-md wlmi-text-sm wlmi-font-medium ${tab.check.includes(currentPath) ? "wlmi-text-blue_primary" : "wlmi-text-light"} `}>
            {tab.label}
        </p>
        {tab.check.includes(currentPath) &&
            <span
                className={`wlmi-h-1 wlmi-absolute wlmi-rounded-t-lg wlmi-bg-blue_primary wlmi-transition wlmi-duration-200 wlmi-ease-in wlmi-bottom-0  wlmi-left-2.5 wlmi-w-[75%]`}/>}
    </Link>
};

export default Tab;
